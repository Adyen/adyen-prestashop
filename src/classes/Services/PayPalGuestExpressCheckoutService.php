<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PaymentRequest\Request\StartTransactionRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
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
     *
     * @throws \Exception
     */
    public function startGuestPayPalPaymentTransaction(Cart $cart, float $orderTotal, array $data = [])
    {
        $currency = new PrestaCurrency((int)Context::getContext()->currency->id);

        $response = CheckoutAPI::get()
            ->paymentRequest((string)$cart->id_shop)
            ->startTransaction(
                new StartTransactionRequest(
                    'paypal',
                    Amount::fromFloat(
                        $orderTotal,
                        Currency::fromIsoCode($currency->iso_code ?? 'EUR')
                    ),
                    (string)$cart->id,
                    Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => 'paypal']
                    ),
                    !empty($data['adyen-additional-data']) ? json_decode(
                        $data['adyen-additional-data'],
                        true
                    ) : []
                )
            );

        die(json_encode(
            [
                'action' => $response->getAction(),
                'reference' => $cart->id,
                'pspReference' => $response->getPspReference(),
            ]
        ));
    }
}
