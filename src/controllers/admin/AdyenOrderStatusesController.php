<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Stores\Exceptions\FailedToRetrieveOrderStatusesException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenOrderStatusesController
 */
class AdyenOrderStatusesController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws FailedToRetrieveOrderStatusesException
     */
    public function displayAjaxGetOrderStatuses(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->store($storeId)->getStoreOrderStatuses();

        AdyenPrestaShopUtility::dieJson($result);
    }
}
