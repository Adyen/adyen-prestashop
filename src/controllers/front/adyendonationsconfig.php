<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
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
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\MissingClientKeyConfiguration
     * @throws \Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ConnectionSettingsNotFountException
     * @throws Exception
     */
    public function postProcess(): void
    {
        $merchantReference = Tools::getValue('merchantReference');

        $cart = new Cart($merchantReference);
        $currency = new Currency($cart->id_currency);
        $currencyFactor = $currency->conversion_rate;

        $result = CheckoutAPI::get()
            ->donation((string)Context::getContext()->shop->id)
            ->getDonationSettings($merchantReference, empty($currencyFactor) ? 1 : $currencyFactor);

        AdyenPrestaShopUtility::dieJson($result);
    }
}
