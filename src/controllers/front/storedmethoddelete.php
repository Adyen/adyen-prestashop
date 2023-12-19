<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutConfig\Request\DisableStoredDetailsRequest;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ConnectionSettingsNotFountException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialStoredMethodDeleteModuleFrontController
 */
class AdyenOfficialStoredMethodDeleteModuleFrontController extends ModuleFrontController
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
     */
    public function postProcess(): void
    {
        $customerId = Context::getContext()->customer->id;
        $methodId = Tools::getValue('methodId');
        if (empty($methodId)) {
            AdyenPrestaShopUtility::die404(
                [
                    'message' => 'Disable action could not be processed, invalid request.'
                ]
            );
        }

        if (empty($customerId)) {
            AdyenPrestaShopUtility::die404(
                [
                    'message' => 'Disable action could not be processed, customer not found.'
                ]
            );
        }

        $shop = Shop::getShop(Context::getContext()->shop->id);
        $disableRequest = new DisableStoredDetailsRequest(
            $shop['domain'] . '_' . Context::getContext()->shop->id . '_' . $customerId,
            $methodId
        );

        $result = CheckoutAPI::get()->checkoutConfig(Context::getContext()->shop->id)->disableStoredDetails(
            $disableRequest
        );

        if (!$result->isSuccessful()) {
            AdyenPrestaShopUtility::die400(
                [
                    'message' => 'Disable action could not be processed.'
                ]
            );
        }

        AdyenPrestaShopUtility::dieJson($result);
    }
}
