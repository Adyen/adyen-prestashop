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
use PrestaShop\PrestaShop\Adapter\CoreException;

class AdyenValidate3dModuleFrontController extends FrontController
{
    /**
     * @throws CoreException
     * @throws AdyenException
     */
    public function postProcess()
    {
        // retrieve cart from temp value and restore the cart to approve payment
        $cart = new \Cart((int)\Tools::getValue('reference'));

        $paymentResponse = $this->adyenPaymentResponseModel->getPaymentResponseByCartId($cart->id);

        if (empty($paymentResponse) ||
            empty($paymentResponse['paymentData'])
        ) {
            // create new cart from the current cart
            $this->cartService->cloneCurrentCart($this->context, $cart);

            $this->logger->error("The payment was cancelled, id:  " . $cart->id);
            $this->setTemplate(
                $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
            );
            return;
        }

        $requestMD = \Tools::getValue('MD');
        $requestPaRes = \Tools::getValue('PaRes');

        $request = array(
            "paymentData" => $paymentResponse['paymentData'],
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
