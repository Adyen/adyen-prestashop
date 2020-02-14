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
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\AdyenException;
use Adyen\PrestaShop\controllers\FrontController;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

class AdyenPaymentModuleFrontController extends FrontController
{
    const IS_AJAX = 'isAjax';
    const DATE_OF_BIRTH = 'dateOfBirth';
    const GENDER = 'gender';
    const TELEPHONE_NUMBER = 'telephoneNumber';
    const ISSUER = 'issuer';
    const PA_REQUEST = 'paRequest';
    const MD = 'md';
    const ISSUER_URL = 'issuerUrl';
    const PAYMENT_DATA = 'paymentData';
    const REDIRECT_METHOD = 'redirectMethod';
    const STORE_DETAILS = 'storeDetails';
    const METHOD = 'method';
    const CARDHOLDER_NAME = 'holderName';
    const ENCRYPTED_CARD_NUMBER = 'encryptedCardNumber';
    const ENCRYPTED_EXPIRY_MONTH = 'encryptedExpiryMonth';
    const ENCRYPTED_EXPIRY_YEAR = 'encryptedExpiryYear';
    const ENCRYPTED_SECURITY_CODE = 'encryptedSecurityCode';
    const PAYMENT_METHOD = 'paymentMethod';
    const TYPE = 'type';
    const STORED_PAYMENT_METHOD_ID = 'storedPaymentMethodId';
    const PERSONAL_DETAILS = 'personalDetails';

    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * @var Adyen\PrestaShop\service\Payment
     */
    private $paymentService;

    /**
     * @var Adyen\PrestaShop\service\builder\Customer
     */
    private $customerBuilder;

    /**
     * @var Adyen\PrestaShop\service\builder\OpenInvoice
     */
    private $openInvoiceBuilder;

    /**
     * @var Adyen\PrestaShop\service\builder\Address
     */
    private $addressBuilder;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Language
     */
    private $languageAdapter;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\State
     */
    private $stateAdapter;

    /**
     * @var Adyen\Util\Currency
     */
    private $utilCurrency;

    /**
     * @var Adyen\PrestaShop\service\builder\Browser
     */
    private $browserBuilder;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Country
     */
    private $countryAdapter;

    /**
     * @var Adyen\PrestaShop\service\builder\Payment
     */
    private $paymentBuilder;

    /**
     * @var Adyen\PrestaShop\service\Gender
     */
    private $genderService;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Configuration
     */
    private $configuration;

    /**
     * AdyenPaymentModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->paymentService = ServiceLocator::get('Adyen\PrestaShop\service\Payment');
        $this->customerBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Customer');
        $this->openInvoiceBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\OpenInvoice');
        $this->addressBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Address');
        $this->languageAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Language');
        $this->stateAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\State');
        $this->utilCurrency = ServiceLocator::get('Adyen\Util\Currency');
        $this->browserBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Browser');
        $this->countryAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Country');
        $this->paymentBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Payment');
        $this->genderService = ServiceLocator::get('Adyen\PrestaShop\service\Gender');
        $this->configuration = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Configuration');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');

        $this->helperData->startSession();
    }

    /**
     * @return mixed|null
     * @throws AdyenException
     */
    public function postProcess()
    {
        unset($_SESSION['paymentAction']);
        $cart = $this->context->cart;
        $isAjax = Tools::getValue(self::IS_AJAX);

        // Handle 3DS1 flow, when the payments call is already done and the details are submitted from the frontend,
        // by the place order button
        if ($this->is3DS1Process()) {
            return $this->handle3DS1();
        }

        // Handle payments call in case there is no payments response saved into the session
        if (empty($_SESSION['paymentsResponse'])) {
            $request = array();

            try {
                $request = $this->buildBrowserData($request);
                $request = $this->buildAddresses($request);
                $request = $this->buildPaymentData($request);
                $request = $this->buildCustomerData($request);
            } catch (MissingDataException $exception) {
                $this->logger->error("There was an error with the payment method. id:  " . $cart->id . " Missing data: " . $exception->getMessage());

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'error',
                        array(
                            'message' => "There was an error with the payment method, please choose another one."
                        )
                    )
                );
            }

            // call adyen library
            /** @var Adyen\PrestaShop\service\Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

            try {
                $response = $service->payments($request);
            } catch (AdyenException $e) {
                $this->logger->error(
                    "There was an error with the payment method. id:  " . $cart->id .
                    " Response: " . $e->getMessage()
                );

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
            $this->redirectUserToPageLink($this->context->link->getPageLink('order', $this->ssl, null, 'step=1'),
                $isAjax);
        }

        $this->handlePaymentsResponse($response, $cart, $customer, $isAjax);

        return $response;
    }

    /**
     * @param $response
     * @param $cart
     * @param $customer
     * @param $isAjax
     * @throws AdyenException
     */
    private function handlePaymentsResponse($response, $cart, $customer, $isAjax)
    {
        $resultCode = $response['resultCode'];

        $extraVars = array();
        if (!empty($response['pspReference'])) {
            $extraVars['transaction_id'] = $response['pspReference'];
        }

        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);

        // Based on the result code start different payment flows
        switch ($resultCode) {
            case 'Authorised':
                $this->module->validateOrder($cart->id, 2, $total, $this->module->displayName, null, $extraVars,
                    (int)$cart->id_currency, false, $customer->secure_key);
                $newOrder = new \Order((int)$this->module->currentOrder);

                if (\Validate::isLoadedObject($newOrder)) {
                    $paymentCollection = $newOrder->getOrderPaymentCollection();
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

                $this->redirectUserToPageLink(
                    $this->context->link->getPageLink(
                        'order-confirmation',
                        $this->ssl,
                        null,
                        'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' .
                        $this->module->currentOrder . '&key=' . $customer->secure_key
                    ),
                    $isAjax
                );

                break;
            case 'Refused':
                // In case of refused payment there is no order created and the cart needs to be cloned and reinitiated
                $this->helperData->cloneCurrentCart($this->context, $cart);
                $this->logger->error("The payment was refused, id:  " . $cart->id);

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

                // Check if redirect shopper response data is valid
                if (empty($response['redirect']['url']) ||
                    empty($response['redirect']['method']) ||
                    empty($response['paymentData'])
                ) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array('message' => "There was an error with the payment method, please choose another one.")
                        )
                    );
                }

                $redirectUrl = $response['redirect']['url'];
                $redirectMethod = $response['redirect']['method'];
                $paymentData = $response['paymentData'];

                // Identify if 3DS1 redirect
                if (!empty($response['redirect']['data']['PaReq']) && !empty($response['redirect']['data']['MD'])) {
                    $paRequest = $response['redirect']['data']['PaReq'];
                    $md = $response['redirect']['data']['MD'];

                    $this->ajaxRender($this->helperData->buildControllerResponseJson(
                        'threeDS1',
                        array(
                            'paRequest' => $paRequest,
                            'md' => $md,
                            'issuerUrl' => $redirectUrl,
                            'paymentData' => $paymentData,
                            'redirectMethod' => $redirectMethod
                        )
                    ));
                } else {
                    $_SESSION['redirectUrl'] = $redirectUrl;
                    $_SESSION['redirectMethod'] = $redirectMethod;
                    $_SESSION['paymentData'] = $paymentData;

                    $cartId = $this->context->cart->id;
                    $this->context->cookie->id_cart = "";
                    $this->context->cookie->id_cart_temp = $cartId;

                    $this->redirectUserToPageLink($redirectUrl, $isAjax);
                }

                break;
            case 'PresentToShopper':

                $_SESSION['paymentAction'] = $response['action'];

                if (\Validate::isLoadedObject($customer)) {

                    $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
                    $extraVars = array();

                    $this->module->validateOrder($cart->id, \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT'), $total,
                        $this->module->displayName, null, $extraVars,
                        $cart->id_currency, false, $customer->secure_key);

                    $this->redirectUserToPageLink($this->context->link->getPageLink('order-confirmation', $this->ssl,
                        null,
                        'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key),
                        $isAjax);
                }

                break;
            default:
                //8_PS_OS_ERROR_ : payment error
                $this->module->validateOrder(
                    $cart->id,
                    8,
                    $total,
                    $this->module->displayName,
                    null,
                    $extraVars,
                    (int)$cart->id_currency,
                    false,
                    $customer->secure_key
                );
                $this->logger->error(
                    "There was an error with the payment method. id:  " . $cart->id .
                    ' Unsupported result code in response: ' . print_r($response, true)
                );

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'error',
                        array('message' => "Unsupported result code: {$response['resultCode']}")
                    )
                );
                break;
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

    /**
     * @return mixed
     */
    private function handle3DS1()
    {
        $paymentData = Tools::getValue(self::PAYMENT_DATA);
        $paRequest = Tools::getValue(self::PA_REQUEST);
        $md = Tools::getValue(self::MD);
        $issuerUrl = Tools::getValue(self::ISSUER_URL);
        $redirectMethod = Tools::getValue(self::REDIRECT_METHOD);

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

        return $this->setTemplate(
            $this->helperData->getTemplateFromModulePath('views/templates/front/redirect.tpl')
        );
    }

    /**
     * @return bool
     */
    private function is3DS1Process()
    {
        $paRequest = Tools::getValue(self::PA_REQUEST);
        $md = Tools::getValue(self::MD);
        $issuerUrl = Tools::getValue(self::ISSUER_URL);
        $paymentData = Tools::getValue(self::PAYMENT_DATA);
        $redirectMethod = Tools::getValue(self::REDIRECT_METHOD);

        if (!empty($paRequest) &&
            !empty($md) &&
            !empty($issuerUrl) &&
            !empty($paymentData) &&
            !empty($redirectMethod)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function isCardPayment()
    {
        $paymentMethod = Tools::getValue(self::PAYMENT_METHOD);

        if (!empty($paymentMethod[self::CARDHOLDER_NAME]) &&
            !empty($paymentMethod[self::ENCRYPTED_CARD_NUMBER]) &&
            !empty($paymentMethod[self::ENCRYPTED_EXPIRY_MONTH]) &&
            !empty($paymentMethod[self::ENCRYPTED_EXPIRY_YEAR])
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function isStoredPaymentMethod()
    {
        $paymentMethod = Tools::getValue(self::PAYMENT_METHOD);

        if (!empty($paymentMethod[self::STORED_PAYMENT_METHOD_ID])) {
            return true;
        }

        return false;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildCardData($request = array())
    {
        $paymentMethod = Tools::getValue(self::PAYMENT_METHOD);

        $encryptedCardNumber = $paymentMethod[self::ENCRYPTED_CARD_NUMBER];
        $encryptedExpiryMonth = $paymentMethod[self::ENCRYPTED_EXPIRY_MONTH];
        $encryptedExpiryYear = $paymentMethod[self::ENCRYPTED_EXPIRY_YEAR];
        $holderName = $paymentMethod[self::CARDHOLDER_NAME];

        if (!empty($paymentMethod[self::TYPE])) {
            $paymentMethodType = $paymentMethod[self::TYPE];
        } else {
            $paymentMethodType = 'scheme';
        }

        if (!empty($paymentMethod[self::ENCRYPTED_SECURITY_CODE])) {
            $encryptedSecurityCode = $paymentMethod[self::ENCRYPTED_SECURITY_CODE];
        } else {
            $encryptedSecurityCode = '';
        }

        if (!empty($paymentMethod[self::STORE_DETAILS])) {
            $storeDetails = $paymentMethod[self::STORE_DETAILS];
        } else {
            $storeDetails = false;
        }

        $origin = $this->configuration->httpHost;

        return $this->paymentBuilder->buildCardData(
            $encryptedCardNumber,
            $encryptedExpiryMonth,
            $encryptedExpiryYear,
            $holderName,
            $origin,
            $encryptedSecurityCode,
            $paymentMethodType,
            $storeDetails,
            $request
        );
    }

    /**
     * @param array $request
     * @return array|mixed
     * @throws MissingDataException
     */
    public function buildPaymentData($request = array())
    {
        $cart = $this->context->cart;

        $formattedValue = $this->utilCurrency->sanitize($cart->getOrderTotal(true, \Cart::BOTH),
            $this->context->currency->iso_code);

        // Retrieve merchant account
        $merchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');

        $returnUrl = $this->context->link->getModuleLink(
            $this->module->name,
            'Result',
            array(),
            $this->ssl
        );

        $request = $this->paymentBuilder->buildPaymentData(
            $this->context->currency->iso_code,
            $formattedValue,
            $cart->id,
            $merchantAccount,
            $returnUrl,
            $request);

        if ($this->isCardPayment()) {
            $request = $this->buildCardData($request);
        } elseif ($this->isStoredPaymentMethod()) {
            $request = $this->buildStoredPaymentData($request);
        } else {
            $request = $this->buildLocalPaymentMethodData($request);
        }

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    private function buildBrowserData($request = array())
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $acceptHeader = $_SERVER['HTTP_ACCEPT'];

        $browserInfo = \Tools::getValue('browserInfo');
        $screenWidth = 0;
        $screenHeight = 0;
        $colorDepth = 0;
        $timeZoneOffset = 0;
        $language = '';
        $javaEnabled = false;

        if (!empty($browserInfo)) {
            if (!empty($browserInfo['screenWidth'])) {
                $screenWidth = $browserInfo['screenWidth'];
            }

            if (!empty($browserInfo['screenHeight'])) {
                $screenHeight = $browserInfo['screenHeight'];
            }

            if (!empty($browserInfo['colorDepth'])) {
                $colorDepth = $browserInfo['colorDepth'];
            }

            if (!empty($browserInfo['timeZoneOffset'])) {
                $timeZoneOffset = $browserInfo['timeZoneOffset'];
            }

            if (!empty($browserInfo['language'])) {
                $language = $browserInfo['language'];
            }

            if (!empty($browserInfo['javaEnabled'])) {
                $javaEnabled = $browserInfo['javaEnabled'];
            }
        }

        return $this->browserBuilder->buildBrowserData(
            $userAgent,
            $acceptHeader,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $timeZoneOffset,
            $language,
            $javaEnabled,
            $request
        );
    }

    /**
     * @param array $request
     * @return array|mixed
     * @throws MissingDataException
     */
    private function buildLocalPaymentMethodData($request = array())
    {
        $cart = $this->context->cart;

        $paymentMethod = Tools::getValue(self::PAYMENT_METHOD);

        if (!empty($paymentMethod[self::TYPE])) {
            $paymentMethodType = $paymentMethod[self::TYPE];
        } else {
            throw new MissingDataException('Payment method type is not sent for local payment method');
        }

        if (!empty($paymentMethod[self::ISSUER])) {
            $issuer = $paymentMethod[self::ISSUER];
        } else {
            $issuer = '';
        }

        $request = $this->paymentBuilder->buildAlternativePaymentMethodData($paymentMethodType, $issuer, $request);

        if ($this->paymentService->isOpenInvoicePaymentMethod($paymentMethodType)) {
            $request = $this->buildOpenInvoiceLines($paymentMethodType, $cart, $request);
        }

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    private function buildAddresses($request = array())
    {
        $cart = $this->context->cart;

        $invoicingAddress = new \Address($cart->id_address_invoice);
        $deliveryAddress = new \Address($cart->id_address_delivery);

        // Invoicing address
        $invoicingAddressCountryCode = $this->countryAdapter->getIsoById($invoicingAddress->id_country);
        $invoicingAddressStateIsoCode = $this->stateAdapter->getIsoById($invoicingAddress->id_state);

        // If iso does not exists for id_state assign default empty string
        if (!$invoicingAddressStateIsoCode) {
            $invoicingAddressStateIsoCode = '';
        }

        $request = $this->addressBuilder->buildBillingAddress(
            $invoicingAddress->address1,
            $invoicingAddress->address2,
            $invoicingAddress->postcode,
            $invoicingAddress->city,
            $invoicingAddressStateIsoCode,
            $invoicingAddressCountryCode,
            $request
        );

        // Delivery address
        $deliveryAddressCountryCode = $this->countryAdapter->getIsoById($deliveryAddress->id_country);
        $deliveryAddressStateIsoCode = $this->stateAdapter->getIsoById($deliveryAddress->id_state);

        // If iso does not exists for id_state assign default empty string
        if (!$deliveryAddressStateIsoCode) {
            $deliveryAddressStateIsoCode = '';
        }

        $request = $this->addressBuilder->buildDeliveryAddress(
            $deliveryAddress->address1,
            $deliveryAddress->address2,
            $deliveryAddress->postcode,
            $deliveryAddress->city,
            $deliveryAddressStateIsoCode,
            $deliveryAddressCountryCode,
            $request
        );

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    private function buildCustomerData($request = array())
    {
        $cart = $this->context->cart;
        $customer = new \CustomerCore($cart->id_customer);
        $language = new \LanguageCore($cart->id_lang);
        $invoicingAddress = new \AddressCore($cart->id_address_invoice);
        $paymentMethod = Tools::getValue(self::PAYMENT_METHOD);


        if (!empty($paymentMethod[self::PERSONAL_DETAILS])) {
            $personalDetails = $paymentMethod[self::PERSONAL_DETAILS];
        } else {
            $personalDetails = array();
        }

        $paymentMethodType = $paymentMethod[self::TYPE];
        $isOpenInvoice = $this->paymentService->isOpenInvoicePaymentMethod($paymentMethodType);

        // Already Adyen formatted gender code
        if (!empty($personalDetails[self::GENDER])) {
            $gender = $personalDetails[self::GENDER];
        } else {
            $gender = $this->genderService->getAdyenGenderValueById($customer->id_gender);
        }

        $localeCode = $this->languageAdapter->getLocaleCode($language);
        if (empty($localeCode)) {
            $localeCode = '';
        }

        if (!empty($personalDetails[self::TELEPHONE_NUMBER])) {
            $telephoneNumber = $personalDetails[self::TELEPHONE_NUMBER];
        } else {
            $telephoneNumber = '';
        }

        if (!empty($personalDetails[self::DATE_OF_BIRTH])) {
            $dateOfBirth = $personalDetails[self::DATE_OF_BIRTH];
        } else {
            $dateOfBirth = '';
        }

        $invoicingAddressCountryCode = $this->countryAdapter->getIsoById($invoicingAddress->id_country);
        if (empty($invoicingAddressCountryCode)) {
            $invoicingAddressCountryCode = '';
        }

        $shopperIp = Tools::getRemoteAddr();
        if (empty($shopperIp)) {
            $shopperIp = '';
        }

        return $this->customerBuilder->buildCustomerData(
            $isOpenInvoice,
            $customer->email,
            $telephoneNumber,
            $gender,
            $dateOfBirth,
            $invoicingAddress->firstname,
            $invoicingAddress->lastname,
            $invoicingAddressCountryCode,
            $localeCode,
            $shopperIp,
            $customer->id,
            $request
        );
    }

    /**
     * @param array $request
     * @return array
     */
    private function buildStoredPaymentData($request = array())
    {
        $paymentMethod = Tools::getValue(self::PAYMENT_METHOD);

        if (!empty($paymentMethod[self::TYPE])) {
            $paymentMethodType = $paymentMethod[self::TYPE];
        } else {
            $paymentMethodType = 'scheme';
        }

        if (!empty($paymentMethod[self::ENCRYPTED_SECURITY_CODE])) {
            $encryptedSecurityCode = $paymentMethod[self::ENCRYPTED_SECURITY_CODE];
        } else {
            $encryptedSecurityCode = '';
        }

        if (!empty($paymentMethod[self::STORED_PAYMENT_METHOD_ID])) {
            $storedPaymentMethodId = $paymentMethod[self::STORED_PAYMENT_METHOD_ID];
        } else {
            $storedPaymentMethodId = '';
        }

        $origin = $this->configuration->httpHost;


        return $this->paymentBuilder->buildStoredPaymentData(
            $paymentMethodType,
            $storedPaymentMethodId,
            $origin,
            $encryptedSecurityCode,
            $request
        );
    }

    /**
     * @param $paymentMethod
     * @param Cart $cart
     * @param $request
     * @return mixed
     */
    private function buildOpenInvoiceLines($paymentMethod, \Cart $cart, $request)
    {
        $products = $cart->getProducts(true);
        $lineItems = array();

        // Build open invoice lines for products in the cart
        foreach ($products as $product) {
            $productPrice = $this->utilCurrency->sanitize($product['price'], $this->context->currency->iso_code);
            $productPriceWithTax = $this->utilCurrency->sanitize($product['price_wt'],
                $this->context->currency->iso_code);
            $tax = $productPriceWithTax - $productPrice;

            $productDescription = trim(strip_tags($product['name']));

            $lineItems[] = $this->openInvoiceBuilder->buildOpenInvoiceLineItem(
                $productDescription,
                $productPrice,
                $tax,
                $product['rate'] * 100,
                $product['quantity'],
                $this->openInvoiceBuilder->getVatCategory($paymentMethod),
                $product['id_product']
            );
        }

        // Array of the discount items with the applied value in the cart
        $discounts = $cart->getCartRules();

        // TODO handle multiple discount lines applied value calculation

        // Build open invoice lines for discounts
        foreach ($discounts as $discount) {
            $discountValue = -$this->utilCurrency->sanitize($discount['value_real'],
                $this->context->currency->iso_code);
            $lineItems[] = $this->openInvoiceBuilder->buildOpenInvoiceLineItem(
                $discount['name'],
                $discountValue,
                0,
                0,
                1,
                'None',
                $discount['id_discount']
            );
        }

        // Build open invoice lines for shipping
        $deliveryCost = $cart->getPackageShippingCost();
        $cartSummary = $cart->getSummaryDetails();
        $carrier = $cartSummary['carrier'];
        $shippingTax = ($cartSummary['total_shipping'] - $cartSummary['total_shipping_tax_exc']) * 100;

        if ($cartSummary['total_shipping']) {
            $shippingTaxRate = $shippingTax * 100 / $cartSummary['total_shipping'];
        } else {
            $shippingTaxRate = 0;
        }

        if ($deliveryCost) {
            $lineItems[] = $this->openInvoiceBuilder->buildOpenInvoiceLineItem(
                $carrier->name,
                $cartSummary['total_shipping'],
                $shippingTax,
                $shippingTaxRate,
                1,
                'None',
                $carrier->id_reference
            );
        }

        if (!empty($lineItems)) {
            $request['lineItems'] = $lineItems;
        }

        return $request;
    }
}
