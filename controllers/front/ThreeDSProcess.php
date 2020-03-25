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
// phpcs:disable PSR1.Classes.ClassDeclaration,Squiz.Classes.ValidClassName

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use PrestaShop\PrestaShop\Adapter\CoreException;
use Adyen\PrestaShop\service\Checkout;
use Adyen\AdyenException;
use Adyen\PrestaShop\controllers\FrontController;

class Adyen_officialThreeDSProcessModuleFrontController extends FrontController
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
                        'message' => "3D secure 2.0 failed, payment data not found"
                    )
                )
            );
        }

        // Add payment data into the request object
        $request = array(
            "paymentData" => $paymentResponse['paymentData']
        );

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
        } else {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => "3D secure 2.0 failed, payload details are not found"
                    )
                )
            );
        }

        // Send the payments details request
        try {
            /** @var Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

            $result = $service->paymentsDetails($request);
        } catch (AdyenException $e) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => '3D secure 2.0 failed'
                    )
                )
            );
        } catch (CoreException $e) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => '3D secure 2.0 failed'
                    )
                )
            );
        }

        // Update saved response for cart
        $this->adyenPaymentResponseModel->updatePaymentResponseByCartId($cart->id, $result['resultCode'], $result);

        // Check if result is challenge shopper, if yes return the token
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'ChallengeShopper' &&
            !empty($result['authentication']['threeds2.challengeToken'])
        ) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'threeDS2',
                    array(
                        'type' => $result['resultCode'],
                        'token' => $result['authentication']['threeds2.challengeToken']
                    )
                )
            );
        }

        // Payment can get back to the original flow
        $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

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
