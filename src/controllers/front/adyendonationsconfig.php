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
        $cart = new Cart($merchantReference);
        $customer = new Customer(Context::getContext()->customer->id);
        if ((int)$customer->id !== (int)$cart->id_customer) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'Cart with ID: ' . $cart->id . ' is not associated with customer' . ($customer->id ? ' with ID: ' . $customer->id : '. Customer is not logged in.')]
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
