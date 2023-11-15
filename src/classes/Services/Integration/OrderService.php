<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService as OrderServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Repositories\TransactionHistoryRepository;
use Adyen\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\Utility\TimeProvider;
use Adyen\Webhook\EventCodes;
use AdyenPayment\Classes\Services\RefundHandler;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Cart;
use DateTime;
use Db;
use Order;
use OrderHistory;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\Currency;
use PrestaShopDatabaseException;
use PrestaShopException;

/**
 * Class OrderService.
 *
 * @package AdyenPayment\Integration
 */
class OrderService implements OrderServiceInterface
{
    /**
     * @var TransactionHistoryRepository
     */
    private $transactionHistoryRepository;

    /**
     * @var VersionHandler
     */
    private $versionHandler;

    /**
     * @param TransactionHistoryRepository $transactionLogRepository
     * @param VersionHandler $versionHandler
     */
    public function __construct(TransactionHistoryRepository $transactionLogRepository, VersionHandler $versionHandler)
    {
        $this->transactionHistoryRepository = $transactionLogRepository;
        $this->versionHandler = $versionHandler;
    }

    /**
     * @param string $merchantReference
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */
    public function orderExists(string $merchantReference): bool
    {
        $cart = new Cart((int)$merchantReference);
        $idOrder = (int)$this->getIdByCartId((int)$merchantReference);
        $order = new Order($idOrder);

        if (!$cart->orderExists() ||
            (int)$cart->id_shop !== (int)StoreContext::getInstance()->getStoreId() ||
            $order->module !== 'adyenofficial' ||
            !$this->transactionHistoryRepository->getTransactionHistory($merchantReference)) {
            return false;
        }

        if (!isset($order->current_state) || (int)$order->current_state === 0) {
            $orderCreationTime = new DateTime($order->date_add);
            $now = TimeProvider::getInstance()->getCurrentLocalTime();
            $passedTimeSinceOrderCreation = $now->getTimestamp() - $orderCreationTime->getTimestamp();
            throw new Exception(
                'Order with cart ID:' . $merchantReference . ' can not be updated, because order is still not initialized. Order is not in initial state after ' . $passedTimeSinceOrderCreation . ' seconds since its creation.'
            );
        }

        return true;
    }

    /**
     * @param Webhook $webhook
     * @param string $statusId
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws InvalidCurrencyCode
     * @throws RepositoryClassException
     */
    public function updateOrderStatus(Webhook $webhook, string $statusId): void
    {
        $idOrder = (int)$this->getIdByCartId((int)$webhook->getMerchantReference());

        if (!$idOrder) {
            return;
        }

        $order = new Order($idOrder);

        if ((int)$statusId && (int)$statusId !== (int)$order->current_state) {
            $history = new OrderHistory();
            $history->id_order = $idOrder;
            $history->id_employee = "0";
            $history->changeIdOrderState((int)$statusId, $idOrder, true);
            $history->add();
        }

        if ($webhook->getEventCode() === EventCodes::REFUND && $webhook->isSuccess()) {
            RefundHandler::handleAdyenSuccessRefund($order, $webhook->getAmount()->getPriceInCurrencyUnits());
        }

        if ($webhook->getEventCode() === EventCodes::REFUND && !$webhook->isSuccess()) {
            RefundHandler::handleAdyenFailRefund($order, $webhook->getAmount()->getPriceInCurrencyUnits());
        }
    }

    /**
     * @param string $merchantReference
     *
     * @return string
     */
    public function getOrderCurrency(string $merchantReference): string
    {
        $cart = new Cart((int)$merchantReference);

        return (new Currency($cart->id_currency))->iso_code;
    }

    /**
     * @param string $merchantReference
     *
     * @return string
     */
    public function getOrderUrl(string $merchantReference): string
    {
        return $this->versionHandler->getOrderUrl($merchantReference);
    }

    /**
     * This function must be used for fetching order id from cart because PrestaShop function: Order::getByCartId won't work for multistore.
     *
     * @param int $cartId
     *
     * @return false|string|null
     */
    private function getIdByCartId(int $cartId)
    {
        return Db::getInstance()->getValue(
            "
                                 SELECT `id_order`
                                 FROM `" . _DB_PREFIX_ . "orders`
                                 WHERE `id_cart` = '" . $cartId . "'
                                 "
        );
    }
}
