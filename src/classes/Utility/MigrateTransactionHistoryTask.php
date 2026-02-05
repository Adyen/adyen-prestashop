<?php

namespace AdyenPayment\Classes\Utility;

use Adyen\Core\BusinessLogic\DataAccess\TransactionHistory\Entities\TransactionHistory as TransactionEntity;
use Adyen\Core\BusinessLogic\DataAccess\TransactionLog\Entities\TransactionLog;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\BusinessLogic\Domain\Connection\Enums\Mode;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Models\CaptureType;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Models\HistoryItem;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Models\TransactionHistory;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionHistoryService;
use Adyen\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use Adyen\Core\BusinessLogic\Domain\Webhook\Services\OrderStatusProvider;
use Adyen\Core\BusinessLogic\TransactionLog\Repositories\TransactionLogRepository;
use Adyen\Core\BusinessLogic\Webhook\Tasks\OrderUpdateTask;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Adyen\Core\Infrastructure\ORM\QueryFilter\Operators;
use Adyen\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use Adyen\Core\Infrastructure\ORM\RepositoryRegistry;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Core\Infrastructure\TaskExecution\QueueItem;
use Adyen\Core\Infrastructure\TaskExecution\QueueService;
use Adyen\Core\Infrastructure\TaskExecution\Task;
use AdyenPayment\Classes\Repositories\OrderRepository;

class MigrateTransactionHistoryTask extends Task
{
    private const BATCH_SIZE = 100;

    private $handledOrders = 0;
    private $textNotificationsOffset = 0;

    /**
     * {@inheritDoc}
     */
    public static function fromArray(array $array)
    {
        $task = new self();
        $task->handledOrders = $array['handledOrders'];
        $task->textNotificationsOffset = $array['textNotificationsOffset'];

        return $task;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'handledOrders' => $this->handledOrders,
            'textNotificationsOffset' => $this->textNotificationsOffset,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws \PrestaShopDatabaseException
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    public function execute()
    {
        $orderIds = $this->getOrderIdsForMigration();

        while (!empty($orderIds)) {
            $this->processOrderIdsBatch($orderIds);
            $this->handledOrders += self::BATCH_SIZE;
            $orderIds = $this->getOrderIdsForMigration();
        }

        $this->reportProgress(90);

        $this->dropLegacyTables();

        $this->reportProgress(100);
    }

    /**
     * @return void
     */
    private function dropLegacyTables()
    {
        DatabaseHandler::dropTable('adyen_notification');
        DatabaseHandler::dropTable('adyen_payment_response');
    }

    /**
     * @param array $orderIds
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws \PrestaShopDatabaseException
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    private function processOrderIdsBatch(array $orderIds)
    {
        foreach ($orderIds as $orderId) {
            $handledNotifications = $this->getHandledNotificationsForOrderId($orderId);
            if (!empty($handledNotifications)) {
                $this->processHandledNotificationsBatch($handledNotifications);
            }

            $this->reportAlive();

            $unhandledNotifications = $this->getUnhandledNotificationsForOrderId($orderId);
            if (!empty($unhandledNotifications)) {
                $this->processUnhandledNotificationsBatch($unhandledNotifications);
            }
        }
    }

    /**
     * @param array $notifications
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     * @throws \Exception
     */
    private function processHandledNotificationsBatch(array $notifications)
    {
        $ordersMap = $this->getOrderMapFor($notifications);
        /** @var TransactionHistory[] $transactionHistoryMap */
        $transactionHistoryMap = $this->getTransactionHistoryMapFor($notifications, $ordersMap);

        foreach ($notifications as $notification) {
            if (!array_key_exists($notification['merchant_reference'], $ordersMap)
                || !array_key_exists($notification['merchant_reference'], $transactionHistoryMap)) {
                continue;
            }

            $order = $ordersMap[$notification['merchant_reference']];
            $transactionHistory = $transactionHistoryMap[$notification['merchant_reference']];

            StoreContext::doWithStore(
                $order['id_shop'],
                function () use ($notification, $order, $transactionHistory) {
                    $this->getTransactionLogRepository()
                        ->setTransactionLog($this->transformNotificationToLog($notification, $order));

                    $this->updateTransactionHistoryWith($transactionHistory, $notification, $order);
                }
            );
        }
    }

    /**
     * @param array $notifications
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     * @throws \Exception
     */
    private function processUnhandledNotificationsBatch(array $notifications)
    {
        $ordersMap = $this->getOrderMapFor($notifications);
        /** @var TransactionHistory[] $transactionHistoryMap */
        $transactionHistoryMap = $this->getTransactionHistoryMapFor($notifications, $ordersMap);

        foreach ($notifications as $notification) {
            if (!array_key_exists($notification['merchant_reference'], $ordersMap)
                || !array_key_exists($notification['merchant_reference'], $transactionHistoryMap)) {
                continue;
            }

            $order = $ordersMap[$notification['merchant_reference']];
            $transactionHistory = $transactionHistoryMap[$notification['merchant_reference']];

            StoreContext::doWithStore(
                $order['id_shop'],
                function () use ($notification, $order, $transactionHistory) {
                    $this->getTransactionHistoryService()->setTransactionHistory($transactionHistory);
                    $this->getQueueService()->enqueue(
                        'OrderUpdate',
                        new OrderUpdateTask(
                            $this->transformNotificationToWebhook($notification, $order, $transactionHistory)
                        )
                    );
                }
            );
        }
    }

    private function transformNotificationToLog(array $notification, array $order): TransactionLog
    {
        $mode = \ConfigurationCore::get('ADYEN_MODE', null, null, $order['id_shop']);

        $transactionLog = new TransactionLog();
        $transactionLog->setStoreId((string) $order['id_shop']);
        $transactionLog->setMerchantReference((string) $order['id_cart']);
        $transactionLog->setExecutionId(0);
        $transactionLog->setEventCode($notification['event_code']);
        $transactionLog->setReason($notification['reason']);
        $transactionLog->setIsSuccessful((bool) $notification['success']);
        $transactionLog->setTimestamp(
            \DateTime::createFromFormat('Y-m-d H:i:s', $notification['created_at'])->getTimestamp()
        );
        $transactionLog->setPaymentMethod($notification['payment_method']);
        $transactionLog->setAdyenLink($this->getAdyenLink($notification['pspreference'], $notification, $mode));
        $transactionLog->setShopLink($this->getOrderService()->getOrderUrl($notification['merchant_reference']));
        $transactionLog->setQueueStatus($notification['success'] ? QueueItem::COMPLETED : QueueItem::FAILED);
        $transactionLog->setPspReference($notification['pspreference']);

        return $transactionLog;
    }

    /**
     * @param TransactionHistory $transactionHistory
     * @param array $notification
     * @param array $order
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     */
    private function updateTransactionHistoryWith(
        TransactionHistory $transactionHistory,
        array $notification,
        array $order
    ) {
        $webhook = $this->transformNotificationToWebhook($notification, $order, $transactionHistory);

        $transactionHistory->add(
            new HistoryItem(
                $webhook->getPspReference(),
                $webhook->getMerchantReference(),
                $webhook->getEventCode(),
                $this->getOrderStatusProvider()->getNewPaymentState($webhook, $transactionHistory),
                $webhook->getEventDate(),
                $webhook->isSuccess(),
                $webhook->getAmount(),
                $webhook->getPaymentMethod(),
                $webhook->getRiskScore(),
                $webhook->isLive()
            )
        );

        $this->getTransactionHistoryService()->setTransactionHistory($transactionHistory);
    }

    /**
     * @param array $notification
     * @param array $order
     * @param TransactionHistory $transactionHistory
     *
     * @return Webhook
     *
     * @throws InvalidCurrencyCode
     */
    private function transformNotificationToWebhook(
        array $notification,
        array $order,
        TransactionHistory $transactionHistory
    ): Webhook {
        $mode = \ConfigurationCore::get('ADYEN_MODE', null, null, $order['id_shop']);

        return new Webhook(
            Amount::fromInt(
                (int) $notification['amount_value'],
                !empty($notification['amount_currency']) ? Currency::fromIsoCode(
                    $notification['amount_currency']
                ) : Currency::getDefault()
            ),
            (string) $notification['event_code'],
            \DateTime::createFromFormat('Y-m-d H:i:s', $notification['created_at'])->format('Y-m-d\TH:i:sP'),
            '',
            '',
            (string) $order['id_cart'],
            (string) $notification['pspreference'],
            (string) $notification['payment_method'],
            (string) $notification['reason'],
            (bool) $notification['success'],
            $transactionHistory->getOriginalPspReference(),
            0,
            $mode === Mode::MODE_LIVE,
            []
        );
    }

    /**
     * @param array $notifications
     * @param array $ordersMap
     *
     * @return array
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    private function getTransactionHistoryMapFor(array $notifications, array $ordersMap): array
    {
        $ordersByTempIdMap = [];
        foreach ($ordersMap as $order) {
            $ordersByTempIdMap[$order['id_cart']] = $order;
        }

        $queryFilter = new QueryFilter();
        $queryFilter->where(
            'merchantReference',
            Operators::IN,
            array_map(
                static function (array $notification) use ($ordersMap) {
                    $merchantReference = '';
                    if (array_key_exists($notification['merchant_reference'], $ordersMap)) {
                        $merchantReference = $ordersMap[$notification['merchant_reference']]['id_cart'];
                    }

                    return $merchantReference;
                },
                $notifications
            )
        );

        /** @var TransactionEntity[] $entities */
        $entities = RepositoryRegistry::getRepository(TransactionEntity::class)->select($queryFilter);
        $transactionHistoryMap = [];
        foreach ($entities as $transactionHistoryEntity) {
            $transactionHistory = $transactionHistoryEntity->getTransactionHistory();
            $order = $ordersByTempIdMap[$transactionHistory->getMerchantReference()];
            $transactionHistoryMap[$order['id_cart']] = $transactionHistory;
        }

        foreach ($notifications as $notification) {
            if (!array_key_exists($notification['merchant_reference'], $ordersMap)) {
                continue;
            }

            $merchantReference = $ordersMap[$notification['merchant_reference']]['id_cart'];
            if (!array_key_exists($notification['merchant_reference'], $transactionHistoryMap)) {
                $transactionHistoryMap[$notification['merchant_reference']] = new TransactionHistory(
                    $merchantReference,
                    CaptureType::unknown(),
                    0,
                    Currency::fromIsoCode(
                        (new \Currency($ordersMap[$notification['merchant_reference']]['id_currency']))->iso_code
                    )
                );
            }
        }

        return $transactionHistoryMap;
    }

    /**
     * @param string $orderId
     *
     * @return array|bool|resource|null
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getUnhandledNotificationsForOrderId(string $orderId)
    {
        $query = new \DbQuery();
        $query->select('*')
            ->from('adyen_notification', 'notification')
            ->where('notification.merchant_reference = ' . $orderId)
            ->where('notification.done = 0')
            ->orderBy('entity_id');

        return \Db::getInstance()->executeS($query);
    }

    /**
     * @param string $orderId
     *
     * @return array|bool|resource|null
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getHandledNotificationsForOrderId(string $orderId)
    {
        $query = new \DbQuery();
        $query->select('*')
            ->from('adyen_notification', 'notification')
            ->where('notification.merchant_reference = ' . $orderId)
            ->where('notification.done = 1')
            ->orderBy('entity_id');

        return \Db::getInstance()->executeS($query);
    }

    /**
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getOrderIdsForMigration(): array
    {
        $dateLimit = (new \DateTime())->sub(new \DateInterval('P30D'));
        $query = new \DbQuery();
        $query->select('distinct merchant_reference')
            ->from('adyen_notification')
            ->where("created_at >= '" . pSQL($dateLimit->format('Y-m-d H:i:s')) . "'")
            ->limit(self::BATCH_SIZE, $this->handledOrders);

        $ids = \Db::getInstance()->executeS($query);

        if (!$ids) {
            return [];
        }

        return array_filter(array_map(static function ($notification) {
            if (is_numeric($notification['merchant_reference'])) {
                return (string) $notification['merchant_reference'];
            }

            return '';
        }, $ids));
    }

    /**
     * @param string $pspReference
     * @param array $notification
     * @param string $mode
     *
     * @return string
     */
    private function getAdyenLink(string $pspReference, array $notification, string $mode): string
    {
        $domain = 'ca-test.adyen.com';

        if (!empty($notification['live'])) {
            $domain = 'ca-live.adyen.com';
        }

        return "https://$domain/ca/ca/config/event-logs.shtml?query=$pspReference";
    }

    /**
     * @param array $notifications
     *
     * @return array
     */
    private function getOrderMapFor(array $notifications): array
    {
        $orders = $this->getOrderRepository()->getOrdersByIds(
            array_map(static function (array $notification) {
                return $notification['merchant_reference'];
            }, $notifications)
        );

        $result = [];

        foreach ($orders as $order) {
            $result[$order['id_cart']] = $order;
        }

        return $result;
    }

    // <editor-fold desc="Service getters" defaultstate="collapsed">

    /**
     * @return OrderRepository
     */
    private function getOrderRepository(): OrderRepository
    {
        return ServiceRegister::getService(OrderRepository::class);
    }

    /**
     * @return TransactionLogRepository
     */
    private function getTransactionLogRepository(): TransactionLogRepository
    {
        return ServiceRegister::getService(TransactionLogRepository::class);
    }

    /**
     * @return OrderService
     */
    private function getOrderService(): OrderService
    {
        return ServiceRegister::getService(OrderService::class);
    }

    /**
     * @return OrderStatusProvider
     */
    private function getOrderStatusProvider(): OrderStatusProvider
    {
        return ServiceRegister::getService(OrderStatusProvider::class);
    }

    /**
     * @return TransactionHistoryService
     */
    private function getTransactionHistoryService(): TransactionHistoryService
    {
        return ServiceRegister::getService(TransactionHistoryService::class);
    }

    /**
     * @return QueueService
     */
    private function getQueueService(): QueueService
    {
        return ServiceRegister::getService(QueueService::class);
    }

    // </editor-fold>
}
