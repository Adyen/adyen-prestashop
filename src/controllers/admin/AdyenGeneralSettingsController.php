<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\GeneralSettings\Request\GeneralSettingsRequest;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\InvalidCaptureDelayException;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\InvalidCaptureTypeException;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\InvalidRetentionPeriodException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\Request;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenGeneralSettingsController
 */
class AdyenGeneralSettingsController extends AdyenBaseController
{
    /**
     * @return void
     */
    public function displayAjaxGetGeneralSettings(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->generalSettings($storeId)->getGeneralSettings();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws InvalidCaptureDelayException
     * @throws InvalidCaptureTypeException
     * @throws InvalidRetentionPeriodException
     */
    public function displayAjaxPutGeneralSettings(): void
    {
        $requestData = Request::getPostData();
        $storeId = Tools::getValue('storeId');

        $generalSettingsRequest = new GeneralSettingsRequest(
            $requestData['basketItemSync'] ?? false,
            $requestData['capture'] ?? '',
            $requestData['captureDelay'] ?? 1,
            $requestData['shipmentStatus'] ?? '',
            $requestData['retentionPeriod'] ?? '',
            $requestData['enablePayByLink'] ?? false,
            $requestData['payByLinkTitle'] ?? '',
            $requestData['defaultLinkExpirationTime'] ?? '7',
                $requestData['executeOrderUpdateSynchronously'] ?? false,
                $requestData['cancelledPartialPayment'] ?? true,
                $requestData['disabledOrderModificationsForFailedRefund'] ?? false
        );

        $result = AdminAPI::get()->generalSettings($storeId)->saveGeneralSettings($generalSettingsRequest);

        AdyenPrestaShopUtility::dieJson($result);
    }
}
