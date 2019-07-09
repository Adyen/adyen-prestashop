<?php
require_once dirname(__FILE__) . '/../../helper/data.php';

class AdyenPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function __construct()
    {

        parent::__construct();
        $this->context = Context::getContext();
        $this->helper_data = new Data();
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $client = $this->helper_data->initializeAdyenClient();
//        todo: applicationInfo, uncomment before release
//        $client->setAdyenPaymentSource($this->helper_data->getModuleName(), $this->helper_data->getModuleVersion());
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

        if ($this->helper_data->isPrestashop16()) {
            $this->setTemplate('payment_return.tpl');
        } else {
            $this->setTemplate('module:adyen/views/templates/front/payment_return.tpl');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $extra_vars = array(
            'transaction_id' => $response['pspReference']
        );

        $this->module->validateOrder($cart->id, 2, $total, $this->module->displayName, null, $extra_vars,
            (int)$currency->id, false, $customer->secure_key);
        $new_order = new Order((int)$this->module->currentOrder);
        if (Validate::isLoadedObject($new_order)) {
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
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
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
            !empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]['variant'])
        ) {
            $request['paymentMethod']['type'] = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]['variant'];
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
            'value' => number_format($cart->getOrderTotal(true, 3), 2, '', '')
        ];


        $request["reference"] = (int)$cart->id;
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
        $merchantAccount = Configuration::get('ADYEN_MERCHANT_ACCOUNT');

        // Assign merchant account to request object
        $request['merchantAccount'] = $merchantAccount;

        return $request;
    }

}