<?php

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\AdyenException;
use Adyen\PrestaShop\controllers\FrontController;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\Logger;
use PrestaShop\PrestaShop\Adapter\CoreException;

class AdyenOfficialResultModuleFrontController extends FrontController
{
    /**
     * @var Logger
     */
    public $adyenLogger;

    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * AdyenOfficialResultModuleFrontController constructor.
     *
     * @throws CoreException
     */
    public function __construct()
    {
        $this->adyenLogger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        parent::__construct();
    }

    /**
     * @throws CoreException
     * @throws AdyenException
     */
    public function postProcess()
    {
        $cart = new \Cart(\Tools::getValue(self::ADYEN_MERCHANT_REFERENCE));
        $payload = \Tools::getAllValues();

        // Validate if cart exists - if not redirect back to order page
        if (!\Validate::isLoadedObject($cart)) {
            \Tools::redirect($this->context->link->getPageLink('order', $this->ssl));
        }

        $request = [
            self::DETAILS_KEY => self::getArrayOnlyWithApprovedKeys($payload, self::DETAILS_ALLOWED_PARAM_KEYS),
        ];

        $response = $this->fetchPaymentDetails($request);

        // Remove stored response since the paymentDetails call is done
        $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

        $customer = new \Customer($cart->id_customer);
        $this->handleAdyenApiResponse($response, $cart, $customer, false);
    }
}
