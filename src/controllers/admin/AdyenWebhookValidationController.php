<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenWebhookValidationController
 */
class AdyenWebhookValidationController extends AdyenBaseController
{
    private const FILE_NAME = 'webhook-validation.json';

    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxValidate(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->webhookValidation($storeId)->validate();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxValidateReport(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->webhookValidation($storeId)->report()->toArray();
        $fileName = tempnam(sys_get_temp_dir(), 'adyen_webhook_validation_info');
        $out = fopen($fileName, 'w');
        fwrite($out, json_encode($result));
        fclose($out);

        AdyenPrestaShopUtility::dieFile($fileName, self::FILE_NAME);
    }
}
