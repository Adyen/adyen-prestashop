<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency as AdyenCurrency;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService as OrderServiceInterface;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Repositories\TransactionHistoryRepository;
use Adyen\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\Utility\TimeProvider;
use Adyen\Webhook\EventCodes;
use AdyenPayment\Classes\Services\AdyenOrderStatusMapping;
use AdyenPayment\Classes\Services\RefundHandler;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Cart;
use Configuration;
use DateTime;
use Db;
use Module;
use Order;
use OrderHistory;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\Currency;
use PrestaShopDatabaseException;
use PrestaShopException;
use Shop;
use Validate;

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
     */
    public function cartExists(string $merchantReference): bool
    {
        $cart = new Cart($merchantReference);

        return Validate::isLoadedObject($cart) && $cart->id;
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
            !$this->transactionHistoryRepository->getTransactionHistory($merchantReference)) {
            throw new Exception('Order with cart ID: ' . $merchantReference . ' still not created.');
        }

        if (!isset($order->current_state) || (int)$order->current_state === 0) {
            $orderCreationTime = new DateTime($order->date_add);
            $now = TimeProvider::getInstance()->getCurrentLocalTime();
            $passedTimeSinceOrderCreation = $now->getTimestamp() - $orderCreationTime->getTimestamp();
            throw new Exception(
                'Order with cart ID:' . $merchantReference . ' can not be updated, because order is still not initialized. Order is not in initial state after ' . $passedTimeSinceOrderCreation . ' seconds since its creation.'
            );
        }

        if ($order->module !== 'adyenofficial') {
            return false;
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
     * @throws Exception
     */
    public function updateOrderStatus(Webhook $webhook, string $statusId): void
    {
        $idOrder = (int)$this->getIdByCartId((int)$webhook->getMerchantReference());

        if (!$idOrder) {
            throw new Exception('Order for cart id: ' . $webhook->getMerchantReference() . ' could not be found.');
        }
        $order = new Order($idOrder);
        $this->setTimezone($order->id_shop);

        if ($order->current_state === AdyenOrderStatusMapping::getPrestaShopOrderStatusId(AdyenOrderStatusMapping::PRESTA_PAYMENT_ERROR)
            && !in_array($webhook->getEventCode(), [EventCodes::REFUND, EventCodes::CANCELLATION])
            && $this->getOrderAmount($webhook->getMerchantReference())->getValue() !== $webhook->getAmount()->getValue()
        ) {
            return;
        }

        if ((int)$statusId && (int)$statusId !== (int)$order->current_state) {
            $history = new OrderHistory();
            $history->id_order = $idOrder;
            $history->id_employee = "0";
            $history->changeIdOrderState((int)$statusId, $idOrder, true);
            $history->add();
            $updatedState = $this->getOrderCurrentState($idOrder);
            if ((int)$updatedState !== (int)$statusId) {
                throw new Exception(
                    'Order status update failed for order with ID: ' . $idOrder . '. Adyen tried to change order state id to ' . $statusId . ' but PrestaShop API failed to update order to desired status. '
                );
            }
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
     * @param Webhook $webhook
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function updateOrderPayment(Webhook $webhook): void
    {
        $idOrder = (int)$this->getIdByCartId((int)$webhook->getMerchantReference());

        if (!$idOrder) {
            return;
        }

        $order = new Order($idOrder);
        $adyenModule = Module::getInstanceByName('adyenofficial');

        if ($order->module !== $adyenModule->name) {
            $order->module = $adyenModule->name;
            $order->payment = $adyenModule->displayName;

            $order->update();
        }
    }

    /**
     * @param string $merchantReference
     *
     * @return Amount
     *
     * @throws InvalidCurrencyCode
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrderAmount(string $merchantReference): Amount
    {
        $idOrder = (int)$this->getIdByCartId((int)$merchantReference);
        $order = new Order($idOrder);
        $currency = new Currency($order->id_currency);
        return Amount::fromFloat((float)$order->getOrdersTotalPaid(),
            AdyenCurrency::fromIsoCode($currency->iso_code));
    }

    /**
     * This function must be used for fetching order id from cart because PrestaShop function: Order::getByCartId won't
     * work for multistore.
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

    /**
     * Gets current order status for order with id provided as parameter.
     *
     * @param int $orderId
     *
     * @return false|string|null
     */
    private function getOrderCurrentState(int $orderId)
    {
        return Db::getInstance()->getValue(
            "
                                 SELECT `current_state`
                                 FROM `" . _DB_PREFIX_ . "orders`
                                 WHERE `id_order` = '" . $orderId . "'
                                 "
        );
    }

    /**
     * @param int $storeId
     *
     * @return void
     */
    private function setTimezone(int $storeId): void
    {
        $shop = new Shop($storeId);

        @date_default_timezone_set(
            Configuration::get(
                'PS_TIMEZONE',
                null,
                $shop->id_shop_group,
                $shop->id,
                Configuration::get('PS_TIMEZONE')
            )
        );
    }

    /**
     * @param Webhook $webhook
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function createOrderFromWebhook(Webhook $webhook): bool
    {
        $cart = new Cart($webhook->getMerchantReference());

        if($cart->orderExists()) {
            return true;
        }

        $inProgressPaymentId = AdminAPI::get()->orderMappings($cart->id_shop)
            ->getOrderStatusMap()->toArray()['inProgress'];
        $module = Module::getInstanceByName('adyenofficial');

        try {
            $success = $module->validateOrder(
                $cart->id,
                (int)$inProgressPaymentId,
                $webhook->getAmount()->getPriceInCurrencyUnits(),
                $module->displayName,
                null,
                [],
                null,
                true,
                $cart->secure_key
            );
        } catch (Exception $e) {
            Logger::logError('Adyen plugin failed to create order with cart id: ' .
                $webhook->getMerchantReference() . ' ,from webhook. Exception' . $e->getMessage()
            );

            $success = false;
        }

        return $success;
    }
}
