<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\Integration\Response\StateResponse;
use AdyenPayment\Classes\Services\ImageHandler;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenDisconnectController
 */
class AdyenDisconnectController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxDisconnect(): void
    {
        $storeId = Tools::getValue('storeId');

        $response = AdminAPI::get()->integration($storeId)->getState();

        if ($response->toArray() === StateResponse::onboarding()->toArray()) {
            AdyenPrestaShopUtility::dieJson($response);
        }

        $result = AdminAPI::get()->disconnect($storeId)->disconnect();

        ImageHandler::removeImage('adyen-giving-logo-store-' . $storeId, $storeId);
        ImageHandler::removeImage('adyen-giving-background-store-' . $storeId, $storeId);
        ImageHandler::removeDirectoryForStore($storeId);

        AdyenPrestaShopUtility::dieJson($result);
    }
}
