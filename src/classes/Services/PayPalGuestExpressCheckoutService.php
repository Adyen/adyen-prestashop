<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PartialPayment\Request\StartPartialTransactionsRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidPaymentMethodCodeException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use AdyenPayment\Classes\Utility\Url;
use Context;
use Currency as PrestaCurrency;
use Cart;

/**
 * Class PayPalGuestExpressCheckoutService
 *
 * @package AdyenPayment\Classes\Services
 */
class PayPalGuestExpressCheckoutService
{
    /**
     * Starts a basic PayPal guest payment transaction with no customer data.
     *
     * @param Cart $cart
     * @param float $orderTotal
     * @param array $data
     * @param array $giftCardsData
     * @return void
     * @throws InvalidCurrencyCode
     * @throws InvalidPaymentMethodCodeException
     * @throws \Exception
     */
    public function startGuestPayPalPaymentTransaction(
        Cart $cart,
        float $orderTotal,
        array $data = [],
        array $giftCardsData = []
    )
    {
        $currency = new PrestaCurrency((int)Context::getContext()->currency->id);

        $response = CheckoutAPI::get()
            ->partialPaymentRequest((string)$cart->id_shop)
            ->startPartialTransactions(
                new StartPartialTransactionsRequest(
                    (string)$cart->id,
                    Currency::fromIsoCode($currency->iso_code ?? 'EUR'),
                    Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => 'paypal']
                    ),
                    $orderTotal,
                    'paypal',
                    $giftCardsData,
                    !empty($data['adyen-additional-data']) ? json_decode(
                        $data['adyen-additional-data'],
                        true
                    ) : []
                )
            );

        die(json_encode(
            [
                'action' => $response->getLatestTransactionResponse()->getAction(),
                'reference' => $cart->id,
                'pspReference' => $response->getLatestTransactionResponse()->getPspReference(),
            ]
        ));
    }
}
