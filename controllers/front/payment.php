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
//        $this->helper_data->adyenLogger()->logDebug(json_$this->context);
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
//        $this->helper_data->adyenLogger()->logDebug(json_encode($_REQUEST));
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $client = $this->helper_data->initializeAdyenClient();
//        todo: applicationInfo, uncomment before release
//        $client->setAdyenPaymentSource($this->helper_data->getModuleName(), $this->helper_data->getModuleVersion());
        $request = [];
        $request = $this->buildCCData($request, $_REQUEST);
        $request = $this->buildPaymentData($request);
        $request = $this->buildMerchantAccountData($request);
        $this->helper_data->adyenLogger()->logDebug($request);

        $this->helper_data->adyenLogger()->logDebug("req1?");
        $this->context->smarty->assign([
            'params' => $_REQUEST,
        ]);
        $this->helper_data->adyenLogger()->logDebug($request);
        // call lib
        $service = new \Adyen\Service\Checkout($client);

        try {
            $response = $service->payments($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        $this->helper_data->adyenLogger()->logDebug($response);
        $this->setTemplate('module:adyen/views/templates/front/after.tpl');
        //todo: check second argument(order status)
        $this->module->validateOrder($cart->id, 1, $cart->getOrderTotal());
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


        $request["reference"] = (int)$this->module->currentOrder;
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