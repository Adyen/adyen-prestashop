<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Models\CaptureType;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Repositories\GeneralSettingsRepository;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use Adyen\Core\BusinessLogic\Webhook\Services\OrderStatusMappingService;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Webhook\PaymentStates;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\SessionService;
use Currency;
use Exception;
use Module;
use Order;
use Tools;

/**
 * Class OrderStatusHandler
 *
 * @package AdyenPayment\Classes\Utility
 */
class OrderStatusHandler
{
    /**
     * @param Order $order
     *
     * @param int $newOrderStatus
     *
     * @return void
     *
     * @throws InvalidMerchantReferenceException
     * @throws RepositoryClassException
     * @throws InvalidCurrencyCode
     * @throws Exception
     */
    public static function handleOrderStatusChange(Order $order, int $newOrderStatus): void
    {
        Bootstrap::init();
        $transactionDetails = TransactionDetailsHandler::getTransactionDetails($order);
        $orderStatusMapping = StoreContext::doWithStore(
            (string)$order->id_shop,
            [self::orderStatusMappingService(), 'getOrderStatusMappingSettings']
        );

        if (empty($transactionDetails)) {
            return;
        }

        $manualCaptureId = self::getManualCaptureStatus((string)$order->id_shop);

        if ($newOrderStatus === (int)$orderStatusMapping[PaymentStates::STATE_CANCELLED]) {
            self::handleCancellation($order, self::isCancellationSupported($transactionDetails));
        }

        if (!empty($manualCaptureId) && (int)$manualCaptureId === $newOrderStatus) {
            self::handleCapture($order, self::isCaptureSupported($transactionDetails));
        }
    }

    /**
     * If order is in cancelled, failed or new status and if payment link is enabled return true.
     *
     * @param Order $order
     *
     * @return bool
     *
     * @throws RepositoryClassException
     * @throws Exception
     */
    public static function shouldGeneratePaymentLinkForNonAdyenOrder(Order $order): bool
    {
        Bootstrap::init();
        $orderStatusMapping = StoreContext::doWithStore(
            (string)$order->id_shop,
            [self::orderStatusMappingService(), 'getOrderStatusMappingSettings']
        );

        /** @var GeneralSettings|null $generalSettings */
        $generalSettings = StoreContext::doWithStore(
            (string)$order->id_shop,
            [self::generalSettingsRepository(), 'getGeneralSettings']
        );

        $paymentLinkEnabled = $generalSettings && $generalSettings->isEnablePayByLink();

        if ($paymentLinkEnabled && (
                (int)$order->current_state === (int)$orderStatusMapping[PaymentStates::STATE_CANCELLED] ||
                (int)$order->current_state === (int)$orderStatusMapping[PaymentStates::STATE_FAILED] ||
                (int)$order->current_state === (int)$orderStatusMapping[PaymentStates::STATE_NEW])

        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array $transactionDetails
     * @return bool
     */
    private static function isCaptureSupported(array $transactionDetails): bool
    {
        return self::isDetailsFlagTrue('captureSupported', $transactionDetails);
    }

    /**
     * @param array $transactionDetails
     * @return bool
     */
    private static function isCancellationSupported(array $transactionDetails): bool
    {
        return self::isDetailsFlagTrue('cancelSupported', $transactionDetails);
    }

    /**
     * @param array $transactionDetails
     * @return bool
     */
    private static function isDetailsFlagTrue(string $flagTocCheck, array $transactionDetails): bool
    {
        foreach ($transactionDetails as $details) {
            foreach ($details as $detail) {
                if (in_array(
                    $detail['eventCode'],
                    [\Adyen\Webhook\EventCodes::ORDER_OPENED, \Adyen\Webhook\EventCodes::ORDER_CLOSED], true
                )) {
                    continue;
                }

                if ($detail[$flagTocCheck]) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Order $order
     * @param bool $captureSupported
     * @param float $capturableAmount
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws Exception
     */
    private static function handleCapture(Order $order, bool $captureSupported): void
    {
        if (!$captureSupported) {
            self::setErrorMessage(Module::getInstanceByName('adyenofficial')->l('Capture is not supported on Adyen.'));

            Tools::redirect(self::orderService()->getOrderUrl((string)$order->id_cart));
        }

        $currency = new Currency($order->id_currency);
        $response = AdminAPI::get()->capture((string)$order->id_shop)->handle(
            (string)$order->id_cart,
            (new \Cart($order->id_cart))->getOrderTotal(),
            $currency->iso_code
        );

        if (!$response->isSuccessful()) {
            self::setErrorMessage(
                Module::getInstanceByName('adyenofficial')->l(
                    'Capture request failed. Please check Adyen configuration. Reason: '
                ) . $response->toArray()['errorMessage'] ?? ''
            );

            Tools::redirect(self::orderService()->getOrderUrl((string)$order->id_cart));
        }

        self::setSuccessMessage(
            Module::getInstanceByName('adyenofficial')->l('Capture request successfully sent to Adyen.')
        );
    }

    /**
     * @param Order $order
     * @param bool $cancelSupported
     *
     * @return void
     *
     * @throws InvalidMerchantReferenceException
     */
    private static function handleCancellation(Order $order, bool $cancelSupported): void
    {
        if (!$cancelSupported) {
            self::setErrorMessage(Module::getInstanceByName('adyenofficial')->l('Cancel is not supported on Adyen.'));

            Tools::redirect(self::orderService()->getOrderUrl((string)$order->id_cart));
        }

        $response = AdminAPI::get()->cancel((string)$order->id_shop)->handle((string)$order->id_cart);

        if (!$response->isSuccessful()) {
            self::setErrorMessage(
                Module::getInstanceByName('adyenofficial')->l(
                    'Cancel request failed. Please check Adyen configuration. Reason: '
                )
                . $response->toArray()['errorMessage'] ?? ''
            );

            Tools::redirect(self::orderService()->getOrderUrl((string)$order->id_cart));
        }

        self::setSuccessMessage(
            Module::getInstanceByName('adyenofficial')->l('Cancellation request successfully sent to Adyen.')
        );
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private static function setSuccessMessage(string $message): void
    {
        SessionService::set('successMessage', $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private static function setErrorMessage(string $message): void
    {
        SessionService::set('errorMessage', $message);
    }

    /**
     * @param string $storeId
     *
     * @return string
     *
     * @throws Exception
     */
    private static function getManualCaptureStatus(string $storeId): string
    {
        $generalSettings = StoreContext::doWithStore(
            $storeId,
            [self::generalSettingsRepository(), 'getGeneralSettings']
        );

        if (!$generalSettings || !$generalSettings->getCapture()->equal(CaptureType::manual())) {
            return '';
        }

        return $generalSettings->getShipmentStatus();
    }

    /**
     * @return GeneralSettingsRepository
     */
    private static function generalSettingsRepository(): GeneralSettingsRepository
    {
        return ServiceRegister::getService(GeneralSettingsRepository::class);
    }

    /**
     * @return OrderStatusMappingService
     */
    private static function orderStatusMappingService(): OrderStatusMappingService
    {
        return ServiceRegister::getService(OrderStatusMappingService::class);
    }

    /**
     * @return OrderService
     */
    private static function orderService(): OrderService
    {
        return ServiceRegister::getService(OrderService::class);
    }
}
