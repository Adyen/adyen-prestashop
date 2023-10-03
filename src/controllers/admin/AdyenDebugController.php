<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\Request;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenDebugController
 */
class AdyenDebugController extends AdyenBaseController
{
    /**
     * @return void
     */
    public function displayAjaxGetDebugMode(): void
    {
        $result = AdminAPI::get()->debug()->getDebugMode();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     */
    public function displayAjaxSetDebugMode(): void
    {
        $requestData = Request::getPostData();

        $result = AdminAPI::get()->debug()->setDebugMode($requestData['debugMode'] ?? false);

        AdyenPrestaShopUtility::dieJson($result);
    }
}
