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
 * Adyen Prestashop Extension
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

class AdyenPaymentModuleFrontController extends \ModuleFrontController
{
    public $ssl = true;

    public function __construct()
    {
        parent::__construct();
        $this->context = \Context::getContext();
        $this->helper_data = new \Adyen\PrestaShop\helper\Data();

        $this->helper_data->startSession();
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        $client = $this->helper_data->initializeAdyenClient();

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

            if ($this->helper_data->isPrestashop16()) {
                return $this->setTemplate('redirect.tpl');
            } else {
                return $this->setTemplate('module:adyen/views/templates/front/redirect.tpl');
            }
        }

        // Handle payments call in case there is no payments response saved into the session
        if (empty($_SESSION['paymentsResponse'])) {

            $request = [];
            $request = $this->buildBrowserData($request);
            $request = $this->buildCCData($request, $_REQUEST);
            $request = $this->buildPaymentData($request);
            $request = $this->buildMerchantAccountData($request);

            // call adyen library
            $service = new \Adyen\Service\Checkout($client);

            try {
                $response = $service->payments($request);
            } catch (\Adyen\AdyenException $e) {
                $this->helper_data->adyenLogger()->logError("There was an error with the payment method. id:  " . $cart->id . " Response: " . $e->getMessage());
            }
        } else {
            // in case the payments response is already present use it from the session then unset it
            $response = $_SESSION['paymentsResponse'];
            unset($_SESSION['paymentsResponse']);
        }

        $customer = new \Customer($cart->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            if (empty($_REQUEST['isAjax'])) {
                \Tools::redirect('index.php?controller=order&step=1');
            } else {
                $this->ajaxDie($this->helper_data->buildControllerResponseJson('redirect', ['redirectUrl' => 'index.php?controller=order&step=1']));
            }
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
        $extra_vars = [];

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
                        if (!empty($response['additionalData']['cardBin'] &&
                            !empty($response['additionalData']['cardSummary']))) {
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

                // Since this controller handles ajax and non ajax form submissions as well, both server side and client side redirects needs to be handled based on the isAjax request parameter
                if (empty($_REQUEST['isAjax'])) {
                    \Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);

                } else {
                    $this->ajaxDie($this->helper_data->buildControllerResponseJson('redirect', ['redirectUrl' => 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key]));
                }

                break;
            case 'Refused':
                // In case of refused payment there is no order created and the cart needs to be cloned and reinitiated
                $this->helper_data->cloneCurrentCart($this->context);
                $this->helper_data->adyenLogger()->logError("The payment was refused, id:  " . $cart->id);
                if ($this->helper_data->isPrestashop16()) {
                    return $this->setTemplate('error.tpl');
                } else {
                    return $this->setTemplate('module:adyen/views/templates/front/error.tpl');
                }
                break;
            case 'IdentifyShopper':
                $this->ajax = true;
                $_SESSION['paymentData'] = $response['paymentData'];

                $this->ajaxDie($this->helper_data->buildControllerResponseJson(
                    'threeDS2',
                    [
                        'type' => 'IdentifyShopper',
                        'token' => $response['authentication']['threeds2.fingerprintToken']
                    ]
                ));

                break;
            case 'ChallengeShopper':
                $this->ajax = true;
                $_SESSION['paymentData'] = $response['paymentData'];

                $this->ajaxDie($this->helper_data->buildControllerResponseJson(
                    'threeDS2',
                    [
                        'type' => 'ChallengeShopper',
                        'token' => $response['authentication']['threeds2.challengeToken']
                    ]
                ));
                break;
            case 'RedirectShopper':
                $this->ajax = true;

                if (!empty($response['redirect']['data']['PaReq']) && !empty($response['redirect']['data']['MD']) && !empty($response['redirect']['url']) && !empty($response['paymentData']) && !empty($response['redirect']['method'])) {

                $paRequest = $response['redirect']['data']['PaReq'];
                $md = $response['redirect']['data']['MD'];
                $issuerUrl = $response['redirect']['url'];
                $paymentData = $response['paymentData'];
                $redirectMethod = $response['redirect']['method'];

                    $this->ajaxDie($this->helper_data->buildControllerResponseJson(
                        'threeDS1',
                        [
                            'paRequest' => $paRequest,
                            'md' => $md,
                            'issuerUrl' => $issuerUrl,
                            'paymentData' => $paymentData,
                            'redirectMethod' => $redirectMethod
                        ]
                    ));
                } else {
                    $this->helper_data->adyenLogger()->logError("3DS secure is not valid. ID:  " . $cart->id);
                }
                break;
            default:
                //8_PS_OS_ERROR_ : payment error
                $this->module->validateOrder($cart->id, 8, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $this->helper_data->adyenLogger()->logError("There was an error with the payment method. id:  " . $cart->id);
                if ($this->helper_data->isPrestashop16()) {
                    return $this->setTemplate('error.tpl');
                } else {
                    return $this->setTemplate('module:adyen/views/templates/front/error.tpl');
                }
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

        // 3DS2 request data
        $request['additionalData']['allow3DS2'] = true;
        $request['origin'] = $this->helper_data->getOrigin();
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



//        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE]) &&
//            $recurringDetailReference = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE]
//        ) {
//            $request['paymentMethod']['recurringDetailReference'] = $recurringDetailReference;
//        }

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
    public function buildPaymentData($request)
    {
        $cart = $this->context->cart;
        $request['amount'] = [
            'currency' => $this->context->currency->iso_code,
            'value' => $this->helper_data->formatAmount($cart->getOrderTotal(true, \Cart::BOTH),
                $this->context->currency->iso_code)
        ];

        $request["reference"] = $cart->id;
        $request["fraudOffset"] = "0";

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildMerchantAccountData($request = [])
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
    public function buildBrowserData($request = [])
    {
        $request['browserInfo'] = [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'acceptHeader' => $_SERVER['HTTP_ACCEPT']
        ];

        return $request;
    }

}