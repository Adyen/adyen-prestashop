<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\AdjustmentRequestAlreadySentException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\AmountNotChangedException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\InvalidAmountException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\InvalidAuthorizationTypeException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\InvalidPaymentStateException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\OrderFullyCapturedException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\PaymentLinkExistsException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Validator\AuthorizationAdjustmentValidator;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\CurrencyMismatchException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Models\TransactionHistory;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionHistoryService;
use Adyen\Core\Infrastructure\ServiceRegister;
use Currency as PrestaCurrency;

/**
 * Class OrderModificationHandler.
 */
class OrderModificationHandler
{
    /**
     * @throws CurrencyMismatchException
     * @throws AdjustmentRequestAlreadySentException
     * @throws PaymentLinkExistsException
     * @throws InvalidAmountException
     * @throws OrderFullyCapturedException
     * @throws InvalidMerchantReferenceException
     * @throws AmountNotChangedException
     * @throws InvalidCurrencyCode
     * @throws InvalidPaymentStateException
     * @throws InvalidAuthorizationTypeException
     */
    public static function handleOrderModification(\Order $order): void
    {
        StoreContext::doWithStore((string) $order->id_shop, function () use ($order) {
            if (!self::shouldSendOrderModification($order)) {
                return;
            }
            $currency = new PrestaCurrency($order->id_currency);
            AdminAPI::get()->authorizationAdjustment((string) $order->id_shop)
                ->handleOrderModification(
                    (string) $order->id_cart,
                    (float) $order->getOrdersTotalPaid(),
                    $currency->iso_code);
        });
    }

    /**
     * @param \Order $order
     *
     * @return bool
     */
    private static function shouldSendOrderModification(\Order $order): bool
    {
        try {
            $transactionHistory = self::getTransactionHistory($order);
            $currency = new PrestaCurrency($order->id_currency);
            $amount = Amount::fromFloat(
                $order->getOrdersTotalPaid(),
                Currency::fromIsoCode($currency->iso_code)
            );
            AuthorizationAdjustmentValidator::validateAdjustmentPossibility($transactionHistory);
            AuthorizationAdjustmentValidator::validateModificationPossibility($transactionHistory, $amount->minus($transactionHistory->getCapturedAmount()));

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * @param \Order $order
     *
     * @return TransactionHistory
     *
     * @throws \Exception
     */
    private static function getTransactionHistory(\Order $order): TransactionHistory
    {
        return StoreContext::doWithStore(
            (string) $order->id_shop,
            [self::getTransactionHistoryService((string) $order->id_shop), 'getTransactionHistory'],
            [(string) $order->id_cart]
        );
    }

    /**
     * @param string $storeId
     *
     * @return TransactionHistoryService
     *
     * @throws \Exception
     */
    private static function getTransactionHistoryService(string $storeId): TransactionHistoryService {
        return StoreContext::doWithStore(
            $storeId,
            [ServiceRegister::getInstance(), 'getService'],
            [TransactionHistoryService::class]
        );
    }
}
