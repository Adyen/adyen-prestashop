<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Proxies\PaymentsProxy;
use Adyen\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\Infrastructure\ServiceRegister;
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
    public function startGuestPayPalPaymentTransaction(Cart $cart, float $orderTotal)
    {
        StoreContext::doWithStore(
            (string)\Context::getContext()->shop->id,
            function () use ($cart, $orderTotal) {
                /** @var ConnectionService $connectionService */
                $connectionService = ServiceRegister::getService(ConnectionService::class);
                /** @var PaymentsProxy $paymentsProxy */
                $paymentsProxy = ServiceRegister::getService(PaymentsProxy::class);

                $currency = new PrestaCurrency((int)Context::getContext()->currency->id);
                $amount =  Amount::fromFloat(
                    $orderTotal,
                    Currency::fromIsoCode($currency->iso_code ?? 'EUR')
                );
                $connectionSettings = $connectionService->getConnectionData();
                $returnUrl = Url::getFrontUrl(
                    'paymentredirect',
                    ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => 'paypal']
                );
                $paymentMethod = [
                    'type' => 'paypal',
                    'subtype' => 'express',
                ];

                $request = new PaymentRequest(
                    $amount,
                    $connectionSettings->getActiveConnectionData()->getMerchantId(),
                    (string)$cart->id,
                    $returnUrl,
                    $paymentMethod
                );

                $response = $paymentsProxy->startPaymentTransaction($request);

                die(json_encode(
                    [
                        'action' => $response->getAction(),
                        'reference' => $cart->id
                    ]
                ));
            }
        );
    }
}
