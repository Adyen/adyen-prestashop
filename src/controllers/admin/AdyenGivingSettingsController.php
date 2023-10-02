<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\AdyenGivingSettings\Request\AdyenGivingSettingsRequest;
use AdyenPayment\Classes\Services\ImageHandler;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenGivingSettingsController
 */
class AdyenGivingSettingsController extends AdyenBaseController
{
    /**
     * @return void
     */
    public function displayAjaxGetAdyenGivingSettings(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->adyenGivingSettings($storeId)->getAdyenGivingSettings();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     */
    public function displayAjaxPutAdyenGivingSettings(): void
    {
        $requestData = Tools::getAllValues();
        $storeId = $requestData['storeId'];

        $result = AdminAPI::get()->adyenGivingSettings($storeId)->saveAdyenGivingSettings(
            $this->createGivingRequest($storeId)
        );

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @param string $storeId
     *
     * @return AdyenGivingSettingsRequest
     */
    private function createGivingRequest(string $storeId): AdyenGivingSettingsRequest
    {
        $requestData = Tools::getAllValues();

        if ($requestData['enableAdyenGiving'] === 'false') {
            ImageHandler::removeImage('adyen-giving-logo-store-' . $storeId, $storeId);
            ImageHandler::removeImage('adyen-giving-background-store-' . $storeId, $storeId);

            return new AdyenGivingSettingsRequest(false);
        }

        $this->saveImages($storeId);

        return new AdyenGivingSettingsRequest(
            $requestData['enableAdyenGiving'] === 'true',
            $requestData['charityName'] ?? '',
            $requestData['charityDescription'] ?? '',
            $requestData['charityMerchantAccount'] ?? '',
            $requestData['donationAmount'] ?? '',
            $requestData['charityWebsite'] ?? '',
            ImageHandler::getImageUrl('adyen-giving-logo-store-' . $storeId, $storeId) ?? '',
            ImageHandler::getImageUrl('adyen-giving-background-store-' . $storeId, $storeId) ?? ''
        );
    }

    /**
     * @param string $storeId
     *
     * @return void
     */
    private function saveImages(string $storeId): void
    {
        $logo = Tools::fileAttachment('logo');

        if ($logo && !ImageHandler::saveImage(
                $logo['tmp_name'],
                'adyen-giving-logo-store-' . $storeId,
                $storeId
            )
        ) {
            AdyenPrestaShopUtility::die400(['message' => 'Error occurred while adding Adyen giving logo image']);
        }

        $backgroundImage = Tools::fileAttachment('backgroundImage');

        if ($backgroundImage && !ImageHandler::saveImage(
                $backgroundImage['tmp_name'],
                'adyen-giving-background-store-' . $storeId,
                $storeId
            )
        ) {
            AdyenPrestaShopUtility::die400(['message' => 'Error occurred while adding Adyen giving background image']);
        }
    }
}
