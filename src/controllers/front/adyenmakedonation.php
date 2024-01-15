<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\Donations\Request\MakeDonationRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ConnectionSettingsNotFountException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialAdyenMakeDonationModuleFrontController
 */
class AdyenOfficialAdyenMakeDonationModuleFrontController extends ModuleFrontController
{
    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws ConnectionSettingsNotFountException
     * @throws Exception
     */
    public function postProcess(): void
    {
        $params = Tools::getAllValues();
        $orderId = Order::getIdByCartId((int)($params['merchantReference']));
        $order = new Order((int)($orderId));

        if (!$params['merchantReference'] || !$params['key'] || !$params['module']) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'There are request parameters missing.']
            );
        }

        if ($params['module'] !== $order->module) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'Module does not match the requested orders module.']
            );
        }

        if ($params['key'] !== $order->secure_key) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'Key does not match the requested orders secure key.']
            );
        }

        $result = CheckoutAPI::get()
            ->donation((string)Context::getContext()->shop->id)
            ->makeDonation(
                new MakeDonationRequest(
                    $params['amount']['value'] ?? '',
                    $params['amount']['currency'] ?? '',
                    $params['merchantReference']
                )
            );

        if (!$result->isSuccessful()) {
            AdyenPrestaShopUtility::die400(
                [
                    'message' => 'Donation failed.'
                ]
            );
        }

        AdyenPrestaShopUtility::dieJson($result);
    }
}
