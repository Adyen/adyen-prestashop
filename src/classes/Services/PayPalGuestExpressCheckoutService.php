<?php

namespace AdyenPayment\Classes\Services;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PartialPayment\Request\StartPartialTransactionsRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidPaymentMethodCodeException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use AdyenPayment\Classes\Utility\Url;
use Currency as PrestaCurrency;

/**
 * Class PayPalGuestExpressCheckoutService
 */
class PayPalGuestExpressCheckoutService
{
    /**
     * Starts a basic PayPal guest payment transaction with no customer data.
     *
     * @param \Cart $cart
     * @param float $orderTotal
     * @param array $data
     * @param array $giftCardsData
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidPaymentMethodCodeException
     * @throws \Exception
     */
    public function startGuestPayPalPaymentTransaction(
        \Cart $cart,
        float $orderTotal,
        array $data = [],
        array $giftCardsData = []
    ) {
        $currency = new PrestaCurrency((int) \Context::getContext()->currency->id);

        $response = CheckoutAPI::get()
            ->partialPaymentRequest((string) $cart->id_shop)
            ->startPartialTransactions(
                new StartPartialTransactionsRequest(
                    (string) $cart->id,
                    Currency::fromIsoCode($currency->iso_code),
                    Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => 'paypal']
                    ),
                    $orderTotal,
                    'paypal',
                    $giftCardsData,
                    $this->getAdditionalData($data)
                )
            );

        exit(json_encode(
            [
                'action' => $response->getLatestTransactionResponse()->getAction(),
                'reference' => $cart->id,
                'pspReference' => $response->getLatestTransactionResponse()->getPspReference(),
            ]
        ));
    }

    /**
     * Safely decodes the Adyen payment state data submitted from the storefront.
     *
     * Returns an empty array when the data is missing or malformed (e.g. when a custom theme
     * interrupts the checkout JS before the component state data is submitted), so that a null
     * is never passed to the strictly-typed StartPartialTransactionsRequest::$additionalData.
     *
     * @param array $data
     *
     * @return array
     */
    private function getAdditionalData(array $data): array
    {
        if (empty($data['adyen-additional-data'])) {
            return [];
        }

        $additionalData = json_decode($data['adyen-additional-data'], true);

        return is_array($additionalData) ? $additionalData : [];
    }
}
