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
class AdyenValidate3dModuleFrontController extends \Adyen\PrestaShop\controllers\FrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->context = \Context::getContext();
        $adyenHelperFactory = new \Adyen\PrestaShop\service\helper\DataFactory();
        $this->helperData = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );
    }

    public function postProcess()
    {
        // retrieve cart from temp value and restore the cart to approve payment
        $cart = new Cart((int)$this->context->cookie->__get("id_cart_temp"));
        $client = $this->helperData->initializeAdyenClient();

        $requestMD = $_REQUEST['MD'];
        $requestPaRes = $_REQUEST['PaRes'];
        $paymentData = $_REQUEST['paymentData'];
        $this->helperData->adyenLogger()->logDebug("md: " . $requestMD);
        $this->helperData->adyenLogger()->logDebug("PaRes: " . $requestPaRes);
        $this->helperData->adyenLogger()->logDebug("request" . json_encode($_REQUEST));
        $request = array(
            "paymentData" => $paymentData,
            "details" => array(
                "MD" => $requestMD,
                "PaRes" => $requestPaRes
            )
        );

        $client->setAdyenPaymentSource(\Adyen\PrestaShop\service\Configuration::MODULE_NAME, \Adyen\PrestaShop\service\Configuration::VERSION);

        try {
            $client = $this->helperData->initializeAdyenClient();
            // call lib
            $service = new \Adyen\Service\Checkout($client);
            $response = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            $this->helperData->adyenLogger()->logError("Error during validate3d paymentsDetails call: exception: " . $e->getMessage());
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson('error', ['message' => "Something went wrong. Please choose another payment method."])
            );
        }
        $this->helperData->adyenLogger()->logDebug("result: " . json_encode($response));
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
                        //todo add !empty
                        $payment[0]->card_number = pSQL($response['additionalData']['cardBin'] . " *** " . $response['additionalData']['cardSummary']);
                        $payment[0]->card_brand = pSQL($response['additionalData']['paymentMethod']);
                        $payment[0]->card_expiration = pSQL($response['additionalData']['expiryDate']);
                        $payment[0]->card_holder = pSQL($response['additionalData']['cardHolderName']);
                        $payment[0]->save();
                    }
                }
                \Tools::redirect($this->context->link->getPageLink('order-confirmation', $this->ssl, null, 'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key));
                break;
            case 'Refused':
                // create new cart from the current cart
                $this->helperData->cloneCurrentCart($this->context, $cart);

                $this->helperData->adyenLogger()->logError("The payment was refused, id:  " . $cart->id);
                return $this->setTemplate($this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl'));
                break;
            default:
                // create new cart from the current cart
                $this->helperData->cloneCurrentCart($this->context, $cart);
                //6_PS_OS_CANCELED_ : order canceled
                $this->module->validateOrder($cart->id, 6, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $this->helperData->adyenLogger()->logError("The payment was cancelled, id:  " . $cart->id);
                return $this->setTemplate($this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl'));
                break;
        }
    }
}