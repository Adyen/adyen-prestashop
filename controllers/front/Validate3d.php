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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

class AdyenValidate3dModuleFrontController extends \Adyen\PrestaShop\controllers\FrontController
{
    /**
     * AdyenValidate3dModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->context = \Context::getContext();
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
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
            /** @var \Adyen\PrestaShop\service\Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');
            $response = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            $this->logger->error("Error during validate3d paymentsDetails call: exception: " . $e->getMessage());
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error', array('message' => "Something went wrong. Please choose another payment method.")
                )
            );
        }
        $this->logger->debug("result: " . json_encode($response));
        $currency = $this->context->currency;
        $customer = new \Customer($cart->id_customer);
        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
        $resultCode = $response['resultCode'];
        $extra_vars = array();
        if (!empty($response['pspReference'])) {
            $extra_vars['transaction_id'] = $response['pspReference'];
        }
        switch ($resultCode) {
            case 'Authorised':
                $this->module->validateOrder($cart->id, 2, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $new_order = new \Order((int)$this->module->currentOrder);
                if (\Validate::isLoadedObject($new_order)) {
                    $payment = $new_order->getOrderPaymentCollection();
                    if (isset($payment[0])) {
                        $cardSummary = !empty($response['additionalData']['cardSummary'])
                            ? pSQL($response['additionalData']['cardSummary'])
                            : '****';
                        $cardBin = !empty($response['additionalData']['cardBin'])
                            ? pSQL($response['additionalData']['cardBin'])
                            : '******';
                        $paymentMethod = !empty($response['additionalData']['paymentMethod'])
                            ? pSQL($response['additionalData']['paymentMethod'])
                            : 'Adyen';
                        $expiryDate = !empty($response['additionalData']['expiryDate'])
                            ? pSQL($response['additionalData']['expiryDate'])
                            : '';
                        $cardHolderName = !empty($response['additionalData']['cardHolderName'])
                            ? pSQL($response['additionalData']['cardHolderName']) : '';
                        $payment[0]->card_number = $cardBin . ' *** ' . $cardSummary;
                        $payment[0]->card_brand = $paymentMethod;
                        $payment[0]->card_expiration = $expiryDate;
                        $payment[0]->card_holder = $cardHolderName;
                        $payment[0]->save();
                    }
                }
                \Tools::redirect($this->context->link->getPageLink('order-confirmation', $this->ssl, null, 'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key));
                break;
            case 'Refused':
                // create new cart from the current cart
                $this->helperData->cloneCurrentCart($this->context, $cart);

                $this->logger->error("The payment was refused, id:  " . $cart->id);
                return $this->setTemplate($this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl'));
                break;
            default:
                // create new cart from the current cart
                $this->helperData->cloneCurrentCart($this->context, $cart);
                //6_PS_OS_CANCELED_ : order canceled
                $this->module->validateOrder($cart->id, 6, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $this->logger->error("The payment was cancelled, id:  " . $cart->id);
                return $this->setTemplate($this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl'));
                break;
        }
    }
}
