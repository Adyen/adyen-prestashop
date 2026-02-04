<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\Payment\Request\PaymentMethodRequest;
use Adyen\Core\BusinessLogic\Domain\Payment\Exceptions\FailedToRetrievePaymentMethodsException;
use Adyen\Core\BusinessLogic\Domain\Payment\Exceptions\PaymentMethodDataEmptyException;
use AdyenPayment\Classes\Services\ImageHandler;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenPaymentController
 */
class AdyenPaymentController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws FailedToRetrievePaymentMethodsException
     */
    public function displayAjaxGetAvailableMethods(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->payment($storeId)->getAvailablePaymentMethods();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws FailedToRetrievePaymentMethodsException
     */
    public function displayAjaxGetConfiguredMethods(): void
    {
        $storeId = Tools::getValue('storeId');

        $result = AdminAPI::get()->payment($storeId)->getConfiguredPaymentMethods();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxGetMethodById(): void
    {
        $storeId = Tools::getValue('storeId');
        $id = Tools::getValue('methodId');

        $result = AdminAPI::get()->payment($storeId)->getMethodById($id);
        if (!$result) {
            AdyenPrestaShopUtility::die404(['message' => 'Not found']);
        }

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws PaymentMethodDataEmptyException
     */
    public function displayAjaxSaveMethod(): void
    {
        $storeId = Tools::getValue('storeId');
        $method = $this->createPaymentMethodRequest();

        $result = AdminAPI::get()->payment($storeId)->saveMethodConfiguration($method);

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws PaymentMethodDataEmptyException
     */
    public function displayAjaxUpdateMethod(): void
    {
        $storeId = Tools::getValue('storeId');
        $method = $this->createPaymentMethodRequest();

        $result = AdminAPI::get()->payment($storeId)->updateMethodConfiguration($method);

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function displayAjaxDeleteMethod(): void
    {
        $storeId = Tools::getValue('storeId');
        $id = Tools::getValue('methodId');

        $result = AdminAPI::get()->payment($storeId)->deletePaymentMethodById($id);

        ImageHandler::removeImage($id, $storeId);

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * Creates payment method request.
     *
     * @return PaymentMethodRequest
     */
    private function createPaymentMethodRequest(): PaymentMethodRequest
    {
        $storeId = Tools::getValue('storeId');
        $requestData = Tools::getAllValues();
        $file = Tools::fileAttachment('logo');

        if ($file && !ImageHandler::saveImage(
            $file['tmp_name'],
            $requestData['methodId'],
            $storeId
        )) {
            AdyenPrestaShopUtility::die400(['message' => 'Error occurred while saving a payment method image']);
        }

        if (!isset($requestData['currencies']) || $requestData['currencies'] === '') {
            $requestData['currencies'] = [];
        }

        if ($requestData['currencies'] === 'ANY') {
            $requestData['currencies'] = ['ANY'];
        }

        if (is_string($requestData['currencies'])) {
            $requestData['currencies'] = [$requestData['currencies']];
        }

        if (!isset($requestData['countries']) || $requestData['countries'] === '') {
            $requestData['countries'] = [];
        }

        if ($requestData['countries'] === 'ANY') {
            $requestData['countries'] = ['ANY'];
        }

        $requestData['additionalData'] = !empty($requestData['additionalData']) ?
            json_decode($requestData['additionalData'], true) : [];

        $requestData['logo'] = $file ? ImageHandler::getImageUrl(
            $requestData['methodId'],
            $storeId
        ) : $requestData['logo'];

        return PaymentMethodRequest::parse($requestData);
    }
}
