<?php

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\WebhookConfigDoesntExistException;
use Adyen\Core\BusinessLogic\WebhookAPI\WebhookAPI;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\HMACKeyValidationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Exception\MerchantAccountCodeException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialWebhooksModuleFrontController
 */
class AdyenOfficialWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * AdyenOfficialWebhooksModuleFrontController constructor.
     *
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Handles incoming Adyen webhook events.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();
    }

    /**
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws WebhookConfigDoesntExistException
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws InvalidDataException
     * @throws MerchantAccountCodeException
     */
    public function postProcess()
    {
        $payload = Tools::file_get_contents('php://input');
        $storeId = Tools::getValue('storeId');
        $result = WebhookAPI::get()->webhookHandler($storeId ?? '')->handleRequest(json_decode($payload, true));

        AdyenPrestaShopUtility::dieJson($result);
    }
}
