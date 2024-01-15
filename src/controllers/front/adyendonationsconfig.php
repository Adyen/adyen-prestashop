<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ConnectionSettingsNotFountException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialAdyenDonationsModuleFrontController
 */
class AdyenOfficialAdyenDonationsConfigModuleFrontController extends ModuleFrontController
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
     * @throws ConnectionSettingsNotFountException
     * @throws Exception
     */
    public function postProcess(): void
    {
        $merchantReference = Tools::getValue('merchantReference');
        $key = Tools::getValue('key');
        $module = Tools::getValue('module');
        $orderId = Order::getIdByCartId((int)($merchantReference));
        $cart = new Cart($merchantReference);
        $order = new Order((int)($orderId));

        if (!$merchantReference || !$key || !$module) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'There are request parameters missing.']
            );
        }

        if ($module !== $order->module) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'Module does not match the requested orders module.']
            );
        }

        if ($key !== $order->secure_key) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'Key does not match the requested orders secure key.']
            );
        }

        $currency = new Currency($cart->id_currency);
        $currencyFactor = $currency->conversion_rate;

        $result = CheckoutAPI::get()
            ->donation((string)Context::getContext()->shop->id)
            ->getDonationSettings($merchantReference, empty($currencyFactor) ? 1 : $currencyFactor);

        AdyenPrestaShopUtility::dieJson($result);
    }
}
