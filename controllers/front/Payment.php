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
use Adyen\PrestaShop\controllers\FrontController;
use \Adyen\AdyenException;

class AdyenPaymentModuleFrontController extends FrontController
{
    public $ssl = true;

    /**
     * AdyenPaymentModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->helperData->startSession();
    }

    /**
     * @return mixed|null
     * @throws AdyenException
     */
    public function postProcess()
    {

        unset($_SESSION['paymentAction']);

        $isAjax = false;
        if (!empty($_REQUEST['isAjax'])) {
            $isAjax = true;
        }

        $cart = $this->context->cart;
        $adyenPaymentType = Tools::getValue('adyen-payment-type');
        if (!empty($adyenPaymentType)) {
            $this->processLocalPaymentMethod($cart, $adyenPaymentType, Tools::getValue('adyen-payment-issuer'), $isAjax);
            return null;
        }
        // Handle 3DS1 flow, when the payments call is already done and the details are submitted from the frontend, by the place order button
        if (!empty($_REQUEST['paRequest']) && !empty($_REQUEST['md']) && !empty($_REQUEST['issuerUrl']) && !empty($_REQUEST['paymentData']) && !empty($_REQUEST['redirectMethod'])) {

            $paRequest = $_REQUEST['paRequest'];
            $md = $_REQUEST['md'];
            $issuerUrl = $_REQUEST['issuerUrl'];
            $paymentData = $_REQUEST['paymentData'];
            $redirectMethod = $_REQUEST['redirectMethod'];

            $termUrl = $this->context->link->getModuleLink("adyen", 'Validate3d',
                array('paymentData' => $paymentData),
                true);

            $this->context->smarty->assign(array(
                'paRequest' => $paRequest,
                'md' => $md,
                'issuerUrl' => $issuerUrl,
                'paymentData' => $paymentData,
                'redirectMethod' => $redirectMethod,
                'termUrl' => $termUrl
            ));

            return $this->setTemplate($this->helperData->getTemplateFromModulePath('views/templates/front/redirect.tpl'));
        }

        // Handle payments call in case there is no payments response saved into the session
        if (empty($_SESSION['paymentsResponse'])) {

            $request = array();
            $request = $this->buildBrowserData($request);
            $request = $this->buildCCData($request, $_REQUEST);
            $request = $this->buildPaymentData($request);
            $request = $this->buildMerchantAccountData($request);
            $request = $this->buildRecurringData($request, $_REQUEST);

            // call adyen library
            /** @var Adyen\PrestaShop\service\Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

            try {
                $response = $service->payments($request);
            } catch (AdyenException $e) {
                $this->helperData->adyenLogger()->logError("There was an error with the payment method. id:  " . $cart->id . " Response: " . $e->getMessage());

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'error',
                        array(
                            'message' => "There was an error with the payment method, please choose another one."
                        )
                    )
                );
            }
        } else {
            // in case the payments response is already present use it from the session then unset it
            $response = $_SESSION['paymentsResponse'];
            unset($_SESSION['paymentsResponse']);
        }

        $customer = new \Customer($cart->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            $this->redirectUserToPageLink($this->context->link->getPageLink('order', $this->ssl, null, 'step=1'), $isAjax);
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
        $extra_vars = array();

        if (!empty($response['pspReference'])) {
            $extra_vars['transaction_id'] = $response['pspReference'];
        }

        // Based on the result code start different payment flows
        $resultCode = $response['resultCode'];

        switch ($resultCode)
        {
            case 'Authorised':
                $this->module->validateOrder($cart->id, 2, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $new_order = new \Order((int)$this->module->currentOrder);

                if (\Validate::isLoadedObject($new_order)) {
                    $paymentCollection = $new_order->getOrderPaymentCollection();
                    foreach ($paymentCollection as $payment) {
                        if (!empty($response['additionalData']['cardBin']) &&
                            !empty($response['additionalData']['cardSummary'])) {
                            $payment->card_number = pSQL($response['additionalData']['cardBin'] . " *** " . $response['additionalData']['cardSummary']);
                        }
                        if (!empty($response['additionalData']['paymentMethod'])) {
                            $payment->card_brand = pSQL($response['additionalData']['paymentMethod']);
                        }
                        if (!empty($response['additionalData']['expiryDate'])) {
                            $payment->card_expiration = pSQL($response['additionalData']['expiryDate']);

                        }
                        if (!empty($response['additionalData']['cardHolderName'])) {
                            $payment->card_holder = pSQL($response['additionalData']['cardHolderName']);
                        }
                        $payment->save();
                    }
                }

                $this->redirectUserToPageLink($this->context->link->getPageLink('order-confirmation', $this->ssl, null, 'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key), $isAjax);

                break;
            case 'Refused':
                // In case of refused payment there is no order created and the cart needs to be cloned and reinitiated
                $this->helperData->cloneCurrentCart($this->context, $cart);
                $this->helperData->adyenLogger()->logError("The payment was refused, id:  " . $cart->id);

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'error',
                        array(
                            'message' => "The payment was refused"
                        )
                    )
                );

                break;
            case 'IdentifyShopper':

                $_SESSION['paymentData'] = $response['paymentData'];

                $this->ajaxRender($this->helperData->buildControllerResponseJson(
                    'threeDS2',
                    array(
                        'type' => 'IdentifyShopper',
                        'token' => $response['authentication']['threeds2.fingerprintToken']
                    )
                ));

                break;
            case 'ChallengeShopper':

                $_SESSION['paymentData'] = $response['paymentData'];

                $this->ajaxRender($this->helperData->buildControllerResponseJson(
                    'threeDS2',
                    array(
                        'type' => 'ChallengeShopper',
                        'token' => $response['authentication']['threeds2.challengeToken']
                    )
                ));
                break;
            case 'RedirectShopper':
                // store cart in tempory value and remove the cart from session
                $cartId = $this->context->cart->id;
                $this->context->cookie->__set("id_cart", "");
                $this->context->cookie->__set("id_cart_temp", $cartId);

                if (!empty($response['redirect']['data']['PaReq']) && !empty($response['redirect']['data']['MD']) && !empty($response['redirect']['url']) && !empty($response['paymentData']) && !empty($response['redirect']['method'])) {

                $paRequest = $response['redirect']['data']['PaReq'];
                $md = $response['redirect']['data']['MD'];
                $issuerUrl = $response['redirect']['url'];
                $paymentData = $response['paymentData'];
                $redirectMethod = $response['redirect']['method'];

                    $this->ajaxRender($this->helperData->buildControllerResponseJson(
                        'threeDS1',
                        array(
                            'paRequest' => $paRequest,
                            'md' => $md,
                            'issuerUrl' => $issuerUrl,
                            'paymentData' => $paymentData,
                            'redirectMethod' => $redirectMethod
                        )
                    ));
                } else {
                    $this->helperData->adyenLogger()->logError("3DS secure is not valid. ID:  " . $cart->id);
                }
                break;
            default:
                //8_PS_OS_ERROR_ : payment error
                $this->module->validateOrder($cart->id, 8, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $this->helperData->adyenLogger()->logError("There was an error with the payment method. id:  " . $cart->id);

                return $this->setTemplate($this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl'));
                break;
        }

        return $response;
    }

    /**
     * @param $request
     * @param $payment
     * @param $storeId
     * @return mixed
     */
    public function buildCCData($request, $payload)
    {
        // If ccType is set use this. For bcmc you need bcmc otherwise it will fail

        if (!empty($payload['method']) && $payload['method'] == 'adyen_oneclick' &&
            !empty($payload[\PaymentInterface::KEY_ADDITIONAL_DATA]['variant'])
        ) {
            $request['paymentMethod']['type'] = $payload[\PaymentInterface::KEY_ADDITIONAL_DATA]['variant'];
        } else {
            $request['paymentMethod']['type'] = 'scheme';
        }

        if (!empty($payload['encryptedCardNumber']) &&
            $cardNumber = $payload['encryptedCardNumber']) {
            $request['paymentMethod']['encryptedCardNumber'] = $cardNumber;
        }

        if (!empty($payload['encryptedExpiryMonth']) &&
            $expiryMonth = $payload['encryptedExpiryMonth']) {
            $request['paymentMethod']['encryptedExpiryMonth'] = $expiryMonth;
        }

        if (!empty($payload['encryptedExpiryYear']) &&
            $expiryYear = $payload['encryptedExpiryYear']) {
            $request['paymentMethod']['encryptedExpiryYear'] = $expiryYear;
        }

        if (!empty($payload['encryptedSecurityCode']) &&
            $securityCode = $payload['encryptedSecurityCode']) {
            $request['paymentMethod']['encryptedSecurityCode'] = $securityCode;
        }

        if (!empty($payload['holderName']) &&
            $holderName = $payload['holderName']) {
            $request['paymentMethod']['holderName'] = $holderName;
        }

        $shopperReference = $this->context->cart->id_customer;
        if(!empty($shopperReference)) {
            $request['shopperReference'] = $shopperReference;
        }

        //storedPayment data
        if(!empty($payload['storedPaymentMethodId'])) {
            $request['paymentMethod']['storedPaymentMethodId'] = $payload['storedPaymentMethodId'];
        }

        // 3DS2 request data
        $request['additionalData']['allow3DS2'] = true;
        $request['origin'] = $this->helperData->getHttpHost();
        $request['channel'] = 'web';

        if (!empty($payload['browserInfo'])) {
            if (!empty($payload['browserInfo']['screenWidth'])) {
                $request['browserInfo']['screenWidth'] = $payload['browserInfo']['screenWidth'];
            }

            if (!empty($payload['browserInfo']['screenHeight'])) {
                $request['browserInfo']['screenHeight'] = $payload['browserInfo']['screenHeight'];
            }

            if (!empty($payload['browserInfo']['colorDepth'])) {
                $request['browserInfo']['colorDepth'] = $payload['browserInfo']['colorDepth'];
            }

            if (!empty($payload['browserInfo']['timeZoneOffset'])) {
                $request['browserInfo']['timeZoneOffset'] = $payload['browserInfo']['timeZoneOffset'];
            }

            if (!empty($payload['browserInfo']['language'])) {
                $request['browserInfo']['language'] = $payload['browserInfo']['language'];
            }

            if (!empty($payload['browserInfo']['javaEnabled'])) {
                $request['browserInfo']['javaEnabled'] = $payload['browserInfo']['javaEnabled'];
            }
        }

        /**
         * if MOTO for backend is enabled use MOTO as shopper interaction type
         */
//        $enableMoto = $this->adyenHelper->getAdyenCcConfigDataFlag('enable_moto', $storeId);
//        if ($areaCode === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE &&
//            $enableMoto
//        ) {
//            $request['shopperInteraction'] = "Moto";
//        }
//
//        // if installments is set add it into the request
//        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS])) {
//            if (($numberOfInstallment = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS]) > 0) {
//                $request['installments']['value'] = $numberOfInstallment;
//            }
//        }

        return $request;
    }

    /**
     * @param $request
     * @return mixed
     */
    public function buildPaymentData($request = array())
    {
        $cart = $this->context->cart;
        $request['amount'] = array(
            'currency' => $this->context->currency->iso_code,
            'value' => $this->helperData->formatAmount($cart->getOrderTotal(true, \Cart::BOTH),
                $this->context->currency->iso_code)
        );

        $request["reference"] = $cart->id;

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildMerchantAccountData($request = array())
    {
        // Retrieve merchant account
        $merchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');

        // Assign merchant account to request object
        $request['merchantAccount'] = $merchantAccount;

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildBrowserData($request = array())
    {
        $request['browserInfo'] = array(
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'acceptHeader' => $_SERVER['HTTP_ACCEPT']
        );

        return $request;
    }

    /**
     * @param array $request
     * @param $payload
     * @return array
     */
    public function buildRecurringData($request = array(), $payload)
    {
        if (!empty($payload['storeCc']) && $payload['storeCc'] === 'true') {
            $request['paymentMethod']['storeDetails'] = true;
            $request['enableOneClick'] = true;
            $request['enableRecurring'] = false;
        }
        return $request;
    }

    private function buildLocalPaymentMethodData($paymentType, $paymentIssuer, $request = array())
    {
        $request['paymentMethod']['type'] = $paymentType;
        if (!empty($paymentIssuer)) {
            $request['paymentMethod']['issuer'] = $paymentIssuer;
        }

        return $request;
    }

    private function buildReturnURL($request)
    {
        $request['returnUrl'] = $this->context->link->getModuleLink(
            $this->module->name,
            'Result',
            array(),
            $this->ssl
        );

        return $request;
    }

    /**
     * @param Cart $cart
     * @param $paymentType
     * @param $paymentIssuer
     * @param $isAjax
     * @throws AdyenException
     */
    private function processLocalPaymentMethod(\Cart $cart, $paymentType, $paymentIssuer, $isAjax)
    {
        $request = array();
        $request = $this->buildBrowserData($request);
        $request = $this->buildLocalPaymentMethodData($paymentType, $paymentIssuer, $request);
        $request = $this->buildPaymentData($request);
        $request = $this->buildMerchantAccountData($request);
        $request = $this->buildReturnURL($request);

        // call adyen library
        /** @var Adyen\PrestaShop\service\Checkout $service */
        $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

        try {
            $response = $service->payments($request);
        } catch (AdyenException $e) {
            $this->helperData->adyenLogger()->logError(
                "There was an error with the payment method. id:  " . $cart->id . " Response: " . $e->getMessage()
            );

            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => "There was an error with the payment method, please choose another one."
                    )
                )
            );
            return;
        }

        switch ($response['resultCode']) {
            case 'RedirectShopper':
                $redirectUrl = null;
                $redirectMethod = null;
                $paymentData = null;

                if (!empty($response['redirect']['url'])) {
                    $redirectUrl = $response['redirect']['url'];
                }

                if (!empty($response['redirect']['method'])) {
                    $redirectMethod = $response['redirect']['method'];
                }

                if (!empty($response['paymentData'])) {
                    $paymentData = $response['paymentData'];
                }

                if ($redirectUrl && $redirectMethod && $paymentData) {
                    $_SESSION['redirectUrl'] = $redirectUrl;
                    $_SESSION['redirectMethod'] = $redirectMethod;
                    $_SESSION['paymentData'] = $paymentData;

                    $cartId = $this->context->cart->id;
                    $this->context->cookie->id_cart = "";
                    $this->context->cookie->id_cart_temp = $cartId;

                    Tools::redirect($redirectUrl);
                    return;
                } else {
                    // invalid parameters
                    $isValid = false;
                }
                break;
            case 'PresentToShopper':

                $_SESSION['paymentAction'] = $response['action'];

                $customer = new \Customer($cart->id_customer);

                if (\Validate::isLoadedObject($customer)) {

                    $currency = $this->context->currency;
                    $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
                    $extra_vars = array();

                    $this->module->validateOrder($cart->id, \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT'), $total, $this->module->displayName, null, $extra_vars,
                        (int)$currency->id, false, $customer->secure_key);

                    $this->redirectUserToPageLink($this->context->link->getPageLink('order-confirmation', $this->ssl, null, 'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key), $isAjax);
                }

                break;
            default:
                $this->helperData->adyenLogger()->logError(
                    'Unsupported result code in response: ' . print_r($response, true)
                );

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'error',
                        array('message' => "Unsupported result code: {$response['resultCode']}")
                    )
                );
                return;
        }
    }

    /**
     * This controller handles ajax and non ajax form submissions as well, both server side and client side redirects
     * needs to be handled based on the $isAjax parameter
     *
     * @param string $pageLink
     * @param bool $isAjax
     * @throws AdyenException
     */
    private function redirectUserToPageLink($pageLink, $isAjax = false)
    {
        if (!$isAjax) {
            \Tools::redirect($pageLink);
        } else {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'redirect', array(
                        'redirectUrl' => $pageLink
                    )
                )
            );
        }
    }
}
