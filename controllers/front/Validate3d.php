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

use Adyen\AdyenException;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\Checkout;
use Adyen\PrestaShop\controllers\FrontController;

class AdyenValidate3dModuleFrontController extends FrontController
{
    /**
     * @throws Adapter_Exception
     * @throws AdyenException
     */
    public function postProcess()
    {
        // retrieve cart from temp value and restore the cart to approve payment
        $cart = new Cart((int)$this->context->cookie->__get("id_cart_temp"));

        $requestMD = $_REQUEST['MD'];
        $requestPaRes = $_REQUEST['PaRes'];
        $paymentData = $_REQUEST['paymentData'];
        $this->logger->debug("md: " . $requestMD);
        $this->logger->debug("PaRes: " . $requestPaRes);
        $this->logger->debug("request" . json_encode($_REQUEST));
        $request = array(
            "paymentData" => $paymentData,
            "details" => array(
                "MD" => $requestMD,
                "PaRes" => $requestPaRes
            )
        );

        try {
            /** @var Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');
            $response = $service->paymentsDetails($request);
        } catch (AdyenException $e) {
            $this->logger->error(
                "Error during validate3d paymentsDetails call: exception: " . $e->getMessage()
            );
            $this->setTemplate(
                $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
            );
            return;
        }

        // Remove payment response for
        $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

        $customer = new \Customer($cart->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            $this->redirectUserToPageLink(
                $this->context->link->getPageLink('order', $this->ssl, null, 'step=1'),
                false
            );
        }

        $this->handlePaymentsResponse($response, $cart, $customer, false);
    }
}
