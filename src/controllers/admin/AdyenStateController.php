<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenStateController
 */
class AdyenStateController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxIndex(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->integration($storeId)->getState();

        AdyenPrestaShopUtility::dieJson($result);
    }
}
