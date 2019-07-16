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
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        $client = $this->helper_data->initializeAdyenClient();
        $request = [];
        $request = $this->buildCCData($request, $_REQUEST);
        $request = $this->buildPaymentData($request);
        $request = $this->buildMerchantAccountData($request);

        $this->context->smarty->assign([
            'params' => $_REQUEST,
        ]);
        // call lib
        $service = new \Adyen\Service\Checkout($client);

        try {
            $response = $service->payments($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        $customer = new \Customer($cart->id_customer);
        if (!\Validate::isLoadedObject($customer)) {
            \Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
        $extra_vars = array(
            'transaction_id' => $response['pspReference']
        );

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
                        if (!empty($response['additionalData']['cardHolderName']) {
                            $payment->card_holder = pSQL($response['additionalData']['cardHolderName']);
                        }
                        $payment->save();
                    }
                }
                \Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
                break;
            case 'Refused':
                //6_PS_OS_CANCELED_ : order canceled
                $this->module->validateOrder($cart->id, 6, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $this->helper_data->adyenLogger()->logError("The payment was refused, id:  " . $cart->id);
                if ($this->helper_data->isPrestashop16()) {
                    return $this->setTemplate('error.tpl');
                } else {
                    return $this->setTemplate('module:adyen/views/templates/front/error.tpl');
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
            'value' => $this->helper_data->formatAmount($cart->getOrderTotal(true, 3), $this->context->currency->iso_code)
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

}