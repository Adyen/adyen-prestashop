<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\PaymentLink\Request\CreatePaymentLinkRequest;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\CookieService;
use Currency as PrestaCurrency;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenPaymentLinkController
 *
 * @package AdyenPayment\Controllers\admin
 */
class AdyenPaymentLinkController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws InvalidMerchantReferenceException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function displayAjaxGeneratePaymentLink(): void
    {
        $orderId = Tools::getValue('orderId');
        $captureAmount = Tools::getValue('amount');
        $order = new Order($orderId);
        $currency = new PrestaCurrency($order->id_currency);

        $response = AdminAPI::get()->paymentLink((string)$order->id_shop)->createPaymentLink(
            new CreatePaymentLinkRequest(!empty($captureAmount) ? $captureAmount : $order->getOrdersTotalPaid(), $currency->iso_code, $order->id_cart)
        );

        if (!$response->isSuccessful()) {

            CookieService::set(
                'errorMessage',
                $this->module->l('Payment link generation failed. Reason: ') . $response->toArray()['errorMessage'] ?? ''
            );
            AdyenPrestaShopUtility::dieJson($response);
        }

        if ($order->module !== $this->module->name) {
            $order->module = $this->module->name;
            $order->payment = Module::getModuleName($this->module->name);;

            $order->update();
        }

        CookieService::set(
            'successMessage',
            $this->module->l('Payment link successfully generated.')
        );

        AdyenPrestaShopUtility::dieJson($response);
    }
}
