<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\InvalidAuthorizationTypeException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\InvalidPaymentStateException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\OrderFullyCapturedException;
use Adyen\Core\BusinessLogic\Domain\AuthorizationAdjustment\Exceptions\PaymentLinkExistsException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\CurrencyMismatchException;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\SessionService;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenAuthorizationAdjustmentController.
 */
class AdyenAuthorizationAdjustmentController extends AdyenBaseController
{
    /**
     * Handles ajax call for extending authorization period.
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws InvalidAuthorizationTypeException
     * @throws InvalidPaymentStateException
     * @throws OrderFullyCapturedException
     * @throws PaymentLinkExistsException
     * @throws CurrencyMismatchException
     * @throws InvalidMerchantReferenceException
     */
    public function displayAjaxExtendAuthorization()
    {
        $orderId = Tools::getValue('orderId');

        $order = new Order($orderId);

        $response = AdminAPI::get()->authorizationAdjustment((string)$order->id_shop)->handleExtendingAuthorizationPeriod((string)$order->id_cart);

        if ($response->isSuccessful()) {
            SessionService::set(
                'successMessage',
                $this->module->l('Authorization adjustment request successfully sent to Adyen.')
            );

            AdyenPrestaShopUtility::dieJson($response);
        }

        SessionService::set(
            'errorMessage',
            $this->module->l('Authorization adjustment request failed. Reason: ') . $response->toArray()['errorMessage'] ?? ''
        );
        AdyenPrestaShopUtility::dieJson($response);
    }
}
