<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\Connection\Request\ConnectionRequest;
use Adyen\Core\BusinessLogic\AdyenAPI\Exceptions\ConnectionSettingsNotFoundException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ApiCredentialsDoNotExistException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ApiKeyCompanyLevelException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyConnectionDataException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyStoreException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidAllowedOriginException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidApiKeyException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidConnectionSettingsException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidModeException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ModeChangedException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\UserDoesNotHaveNecessaryRolesException;
use Adyen\Core\BusinessLogic\Domain\Merchant\Exceptions\ClientKeyGenerationFailedException;
use Adyen\Core\BusinessLogic\Domain\Merchant\Exceptions\ClientPrefixDoesNotExistException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\FailedToGenerateHmacException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\FailedToRegisterWebhookException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\MerchantDoesNotExistException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\Request;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenAuthorizationController
 */
class AdyenAuthorizationController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws ConnectionSettingsNotFoundException
     * @throws ApiCredentialsDoNotExistException
     * @throws ApiKeyCompanyLevelException
     * @throws EmptyConnectionDataException
     * @throws EmptyStoreException
     * @throws InvalidAllowedOriginException
     * @throws InvalidApiKeyException
     * @throws InvalidConnectionSettingsException
     * @throws InvalidModeException
     * @throws ModeChangedException
     * @throws UserDoesNotHaveNecessaryRolesException
     * @throws ClientKeyGenerationFailedException
     * @throws FailedToGenerateHmacException
     * @throws FailedToRegisterWebhookException
     * @throws MerchantDoesNotExistException
     * @throws ClientPrefixDoesNotExistException
     */
    public function displayAjaxConnect(): void
    {
        $requestData = Request::getPostData();
        $storeId = Tools::getValue('storeId');

        $connectionRequest = new ConnectionRequest(
            $storeId,
            $requestData['mode'] ?? '',
            $requestData['testData']['apiKey'] ?? '',
            $requestData['testData']['merchantId'] ?? '',
            $requestData['liveData']['apiKey'] ?? '',
            $requestData['liveData']['merchantId'] ?? ''
        );

        $result = AdminAPI::get()->connection($storeId)->connect($connectionRequest);

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     */
    public function displayAjaxGetConnectionSettings(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->connection($storeId)->getConnectionSettings();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxReRegisterWebhooks(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->connection($storeId)->reRegisterWebhook();

        AdyenPrestaShopUtility::dieJson($result);
    }
}
