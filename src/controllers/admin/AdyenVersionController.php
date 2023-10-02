<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenVersionController
 */
class AdyenVersionController extends AdyenBaseController
{
    /**
     * @return void
     */
    public function displayAjaxGetVersion(): void
    {
        $result = AdminAPI::get()->versions()->getVersionInfo();

        AdyenPrestaShopUtility::dieJson($result);
    }
}
