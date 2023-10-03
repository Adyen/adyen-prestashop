<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Merchant\Exceptions\FailedToRetrieveMerchantsException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenMerchantController
 */
class AdyenMerchantController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws FailedToRetrieveMerchantsException
     * @throws Exception
     */
    public function displayAjaxIndex(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->merchant($storeId)->getMerchants();

        AdyenPrestaShopUtility::dieJson($result);
    }
}
