<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\SessionService;

/**
 * Class AdyenCaptureController
 */
class AdyenCaptureController extends AdyenBaseController
{
    /**
     *  Handles ajax call for order capture.
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     */
    public function displayAjaxCaptureOrder()
    {
        $orderId = Tools::getValue('orderId');
        $captureAmount = Tools::getValue('captureAmount');
        $order = new Order($orderId);
        $currency = new Currency($order->id_currency);
        $pspReference = Tools::getValue('pspReference');

        $response = AdminAPI::get()->capture((string) $order->id_shop)->handle(
            (string) $order->id_cart,
            (float) $captureAmount,
            $currency->iso_code,
            $pspReference
        );

        if ($response->isSuccessful()) {
            SessionService::set(
                'successMessage',
                $this->module->l('Capture request successfully sent to Adyen.')
            );

            AdyenPrestaShopUtility::dieJson($response);
        }

        SessionService::set(
            'errorMessage',
            $this->module->l('Capture request failed. Please check Adyen configuration. Reason: ') . ($response->toArray(
            )['errorMessage'] ?? '')
        );
        AdyenPrestaShopUtility::dieJson($response);
    }
}
