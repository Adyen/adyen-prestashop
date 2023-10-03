<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\Donations\Request\MakeDonationRequest;
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
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode
     * @throws \Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ConnectionSettingsNotFountException
     * @throws Exception
     */
    public function postProcess(): void
    {
        $params = Tools::getAllValues();

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
