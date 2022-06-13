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
 * @copyright (c) 2022 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use PrestaShop\PrestaShop\Adapter\CoreException;
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
        $detailsResponse = null;
        $payload = \Tools::getAllValues();

        $cart = $this->getCurrentCart();
        $payment = $this->adyenPaymentResponseModel->getPaymentByCartId($cart->id);

        if (!array_key_exists(self::DETAILS_KEY, $payload)) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson('error', [
                    'message' => "Something went wrong. Please place the order again!"
                ])
            );
        }

        // Validate if cart amount or currency hasn't changed
        if (!$this->validateCartOrderTotalAndCurrency(
            $cart,
            $payment['request_amount'],
            $payment['request_currency']
        )) {
            $this->logger->addWarning(
                'The cart (id: "' . $cart->id . '") amount or currency has changed during the payment ' .
                'details request with the previous warning log details for this cart'
            );
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    [
                        'message' => 'Something went wrong. Please refresh your page, check your cart and place the ' .
                            'order again!'
                    ]
                )
            );
        }

        $request = [
            self::DETAILS_KEY => $payload[self::DETAILS_KEY],
            self::PAYMENT_DATA => $payment['response']['action'][self::PAYMENT_DATA]
        ];

        try {
            $detailsResponse = $this->fetchPaymentDetails($request);
        } catch (AdyenException $e) {
            $detailsResponse['resultCode'] = 'Error';
        } catch (CoreException $e) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    [
                        'message' => 'The payment failed, please try again with another payment method!'
                    ]
                )
            );
        }

        if (is_null($detailsResponse) || !array_key_exists(self::RESULT_CODE, $detailsResponse)) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    [
                        'message' => 'The payment failed, please try again with another payment method!'
                    ]
                )
            );
        }

        // Update saved response for cart
        $this->adyenPaymentResponseModel->insertOrUpdatePaymentResponse(
            $cart->id,
            $detailsResponse[self::RESULT_CODE],
            $detailsResponse
        );
        $customer = new \Customer($cart->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            $this->redirectUserToPageLink(
                $this->context->link->getPageLink('order', $this->ssl, null, 'step=1'),
                true
            );
        }

        $this->handleAdyenApiResponse($detailsResponse, $cart, $customer, true);
    }
}
