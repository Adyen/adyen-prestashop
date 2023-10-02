<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Stores\Exceptions\FailedToRetrieveStoresException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenShopInformationController
 */
class AdyenShopInformationController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws FailedToRetrieveStoresException
     */
    public function displayAjaxGetStores(): void
    {
        $result = AdminAPI::get()->store('')->getStores();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws FailedToRetrieveStoresException
     */
    public function displayAjaxGetCurrentStore(): void
    {
        $result = AdminAPI::get()->store('')->getCurrentStore();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     */
    public function displayAjaxSwitchContext(): void
    {
        $id = Tools::getValue('storeId');
        $this->context->cookie->shopContext = "s-" . $id;

        AdyenPrestaShopUtility::dieJson();
    }
}
