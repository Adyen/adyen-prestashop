<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\OrderMappings\Request\OrderMappingsRequest;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\Request;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenOrderStatusMapController
 */
class AdyenOrderStatusMapController extends AdyenBaseController
{
    /**
     * @return void
     */
    public function displayAjaxGetOrderStatusMap(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->orderMappings($storeId)->getOrderStatusMap();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     */
    public function displayAjaxPutOrderStatusMap(): void
    {
        $requestData = Request::getPostData();
        $storeId = Tools::getValue('storeId');
        $orderStatusMapRequest = OrderMappingsRequest::parse($requestData);

        $result = AdminAPI::get()->orderMappings($storeId)->saveOrderStatusMap($orderStatusMapRequest);

        AdyenPrestaShopUtility::dieJson($result);
    }
}
