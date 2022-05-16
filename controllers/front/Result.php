<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\controllers\FrontController;
use Adyen\AdyenException;
use Adyen\PrestaShop\service\Logger;
use PrestaShop\PrestaShop\Adapter\CoreException;
use Adyen\PrestaShop\service\Checkout;

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
        // Retrieve cart based on the reference parameter
        $cart = new \Cart(\Tools::getValue(self::ADYEN_MERCHANT_REFERENCE));

        // Validate if cart exists - if not redirect back to order page
        if (!\Validate::isLoadedObject($cart)) {
            \Tools::redirect($this->context->link->getPageLink('order', $this->ssl));
        }

        // Retrieve previous payment response to validate the payment via the paymentDetails request
        $paymentResponse = $this->adyenPaymentResponseModel->getPaymentResponseByCartId($cart->id);
        $details = self::getArrayOnlyWithApprovedKeys(\Tools::getAllValues(), self::DETAILS_ALLOWED_PARAM_KEYS);
        $request['details'] = $details;

        /** @var Checkout $checkout */
        $checkout = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');
        $response = $checkout->paymentsDetails($request);

        // Remove stored response since the paymentDetails call is done
        $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

        $customer = new \Customer($cart->id_customer);
        $this->handleAdyenApiResponse($response, $cart, $customer, false);
    }
}
