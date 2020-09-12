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
use PrestaShop\PrestaShop\Adapter\CoreException;
use Adyen\PrestaShop\service\Checkout;
use Adyen\AdyenException;
use Adyen\PrestaShop\controllers\FrontController;

class AdyenOfficialPaymentsDetailsModuleFrontController extends FrontController
{
    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * @throws AdyenException
     */
    public function postProcess()
    {
        $payload = $_REQUEST;

        $cart = $this->getCurrentCart();
        $paymentResponse = $this->adyenPaymentResponseModel->getPaymentResponseByCartId($cart->id);

        if (empty($paymentResponse) ||
            empty($paymentResponse['paymentData'])
        ) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => "Payment data not found"
                    )
                )
            );
        }

        // Get validated state data
        $request = $this->getValidatedAdditionalData($payload);

        // Add payment data into the request object
        $request["paymentData"] = $paymentResponse['paymentData'];

        // Send the payments details request
        try {
            /** @var Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

            $result = $service->paymentsDetails($request);
        } catch (AdyenException $e) {
            $result['resultCode'] = 'Error';
        } catch (CoreException $e) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => 'The payment failed, please try again with another payment method!'
                    )
                )
            );
        }

        // Update saved response for cart
        $this->adyenPaymentResponseModel->updatePaymentResponseByCartId($cart->id, $result['resultCode'], $result);


        $customer = new \Customer($cart->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            $this->redirectUserToPageLink(
                $this->context->link->getPageLink('order', $this->ssl, null, 'step=1'),
                true
            );
        }

        $this->handlePaymentsResponse($result, $cart, $customer, true);
    }
}
