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

namespace Adyen\PrestaShop\helper;

use Address;
use Adyen;
use Adyen\AdyenException;
use Adyen\PrestaShop\service\adapter\classes\Configuration;
use Adyen\PrestaShop\service\Checkout;
use Adyen\PrestaShop\service\CheckoutUtility;
use Country;
use Currency;

class Data
{
    /**
     * @var string
     */
    private $httpHost;

    /**
     * @var string
     */
    private $sslEncryptionKey;

    /**
     * @var CheckoutUtility
     */
    private $adyenCheckoutUtilityService;

    /**
     * @var Checkout
     */
    private $adyenCheckoutService;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(
        Configuration $configuration,
        CheckoutUtility $adyenCheckoutUtilityService,
        Checkout $adyenCheckoutService
    ) {
        $this->httpHost = $configuration->httpHost;
        $this->sslEncryptionKey = $configuration->sslEncryptionKey;
        $this->adyenCheckoutUtilityService = $adyenCheckoutUtilityService;
        $this->adyenCheckoutService = $adyenCheckoutService;
        $this->configuration = $configuration;
    }

    /**
     * Get origin key for a specific origin using the adyen api library client
     *
     * @return string
     */
    public function getOriginKeyForOrigin()
    {
        $params = array("originDomains" => array($this->httpHost));

        try {
            $response = $this->adyenCheckoutUtilityService->originKeys($params);
        } catch (AdyenException $e) {
            $this->adyenLogger()->logError("exception: " . $e->getMessage());
        }

        $originKey = "";

        // TODO: improve error treatment
        if (!empty($response['originKeys'][$this->httpHost])) {
            $originKey = $response['originKeys'][$this->httpHost];
        } else {
            $this->adyenLogger()->logError("OriginKey is empty, please verify that your API key is correct");
        }

        return $originKey;
    }

    /**
     * @param $cart
     * @param $language
     *
     * @return array
     */
    public function fetchPaymentMethods($cart, $language)
    {
        $merchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');

        if (!$merchantAccount) {
            $this->adyenLogger()->logError(
                "The merchant account field is empty, check your Adyen configuration in Prestashop."
            );
            return array();
        }

        $amount = $cart->getOrderTotal();
        $currencyData = Currency::getCurrency($cart->id_currency);
        $currency = $currencyData['iso_code'];
        $address = new Address($cart->id_address_invoice);
        $countryCode = Country::getIsoById($address->id_country);
        $shopperReference = $cart->id_customer;
        $shopperLocale = $this->getLocale($language);

        $adyenFields = array(
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $countryCode,
            "amount" => array(
                "currency" => $currency,
                "value" => $this->formatAmount(
                    $amount,
                    $currency
                ),
            ),
            "shopperReference" => $shopperReference,
            "shopperLocale" => $shopperLocale
        );

        $responseData = "";
        try {
            $responseData = $this->adyenCheckoutService->paymentMethods($adyenFields);
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger()->logError("There was an error retrieving the payment methods. message: " . $e->getMessage());
        }
        return $responseData;
    }

    public function isDemoMode()
    {
        if (strpos($this->configuration->adyenMode, \Adyen\Environment::TEST) !== false) {
            return true;
        } else {
            return false;
        }
    }

    public function adyenLogger()
    {
        // TODO: debug level should be in configuration
        $logger = new \FileLogger(0); //0 == debug level, logDebug() won’t work without this.

        if (version_compare(_PS_VERSION_, '1.6', '>=') &&
            version_compare(_PS_VERSION_, '1.7', '<')
        ) {
            $dirPath = _PS_ROOT_DIR_ . '/log/adyen';
        } else {
            $dirPath = _PS_ROOT_DIR_ . '/var/logs/adyen';
        }
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0777, true);
        }
        $logger->setFilename($dirPath . '/debug.log');

        return $logger;
    }

    public function encrypt($data)
    {
        // Generate an initialization vector
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-ctr'));
        // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
        $encrypted = openssl_encrypt($data, 'aes-256-ctr', $this->sslEncryptionKey, 0, $iv);
        // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * @param $data
     * @return false|string
     */
    public function decrypt($data)
    {
        if (empty($data)) {
            $this->adyenLogger()->logDebug("decrypt got empty parameter");
            return '';
        }

        // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
        list($data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($data, 'aes-256-ctr', $this->sslEncryptionKey, 0, $iv);
    }

    /**
     * Determine if PrestaShop is 1.6
     *
     * @return bool
     * @deprecated use Adyen\PrestaShop\application\VersionChecker instead
     */
    public function isPrestashop16()
    {
        if (version_compare(_PS_VERSION_, '1.6', '>=') &&
            version_compare(_PS_VERSION_, '1.7', '<')
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param $action
     * @param array $details
     * @return false|string
     * @throws AdyenException
     */
    public function buildControllerResponseJson($action, $details = array())
    {
        switch ($action) {
            case 'error':

                if (empty($details['message'])) {
                    throw new AdyenException('No message is included in the error response');
                }

                $response = array(
                    'action' => 'error',
                    'message' => $details['message']
                );

                break;
            case 'threeDS2':

                $response = array(
                    'action' => 'threeDS2'
                );

                if (!empty($details['type']) && !empty($details['token'])) {
                    $response['type'] = $details['type'];
                    $response['token'] = $details['token'];
                }

                break;
            case 'redirect':

                if (empty($details['redirectUrl'])) {
                    throw new AdyenException('No redirect url is included in the redirect response');
                }

                $response = array(
                    'action' => 'redirect',
                    'redirectUrl' => $details['redirectUrl']
                );

                break;
            case 'threeDS1':

                if (!empty($details['paRequest']) &&
                    !empty($details['md']) &&
                    !empty($details['issuerUrl']) &&
                    !empty($details['paymentData']) &&
                    !empty($details['redirectMethod'])) {
                    $response = array(
                        'action' => 'threeDS1',
                        'paRequest' => $details['paRequest'],
                        'md' => $details['md'],
                        'issuerUrl' => $details['issuerUrl'],
                        'paymentData' => $details['paymentData'],
                        'redirectMethod' => $details['redirectMethod']
                    );
                } else {
                    throw new AdyenException("3DS1 details missing");
                }
                break;
            default:
            case 'error': // this case is never executed

                $response = array(
                    'action' => 'error',
                    'message' => 'Something went wrong'
                );

                break;
        }

        return \Tools::jsonEncode($response);
    }

    /**
     * Return the formatted currency. Adyen accepts the currency in multiple formats.
     * @param $amount
     * @param $currency
     * @return string
     */
    public function formatAmount($amount, $currency)
    {
        switch ($currency) {
            case "CVE":
            case "DJF":
            case "GNF":
            case "IDR":
            case "JPY":
            case "KMF":
            case "KRW":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VND":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
                $format = 0;
                break;
            case "BHD":
            case "IQD":
            case "JOD":
            case "KWD":
            case "LYD":
            case "OMR":
            case "TND":
                $format = 3;
                break;
            default:
                $format = 2;
        }

        return (int)number_format($amount, $format, '', '');
    }

    /**
     * @param $context
     * @param $cart
     *
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function cloneCurrentCart($context, $cart)
    {
        // To save the secure key of current cart id and reassign the same to new cart
        $old_cart_secure_key = $cart->secure_key;
        // To save the customer id of current cart id and reassign the same to new cart
        $old_cart_customer_id = (int)$cart->id_customer;

        // To fetch the current cart products
        $cart_products = $cart->getProducts();
        // Creating new cart object
        $context->cart = new \Cart();
        $context->cart->id_lang = $context->language->id;

        $context->cart->id_currency = $context->currency->id;
        $context->cart->secure_key = $old_cart_secure_key;
        // to add new cart
        $context->cart->add();
        // to update the new cart
        foreach ($cart_products as $product) {
            $context->cart->updateQty((int)$product['quantity'], (int)$product['id_product'],
                (int)$product['id_product_attribute']);
        }
        if ($context->cookie->id_guest) {
            $guest = new \Guest($context->cookie->id_guest);
            $context->cart->mobile_theme = $guest->mobile_theme;
        }
        // to map the new cart with the customer
        $context->cart->id_customer = $old_cart_customer_id;
        // to save the new cart
        $context->cart->save();
        if ($context->cart->id) {
            $context->cookie->id_cart = (int)$context->cart->id;
            $context->cookie->write();
        }
    }

    /**
     * Start the session if does not exists yet
     */
    public function startSession()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    /**
     * Get locale for 1.6/1.7
     * @return mixed
     */
    public function getLocale($language)
    {
        // no locale in PrestaShop1.6 only languageCode that is en-en but we need en_EN
        if ($this->isPrestashop16()) {
            return $language->iso_code;
        } else {
            return $language->locale;
        }
    }

    /**
     * Return the required template path for 1.6 or 1.7
     * Include the full path in the module like: views/templates/front/redirect.tpl
     *
     * @param $templatePath
     * @return string
     */
    public function getTemplateFromModulePath($templatePath)
    {
        if ($this->isPrestashop16()) {
            return basename($templatePath);
        }

        return 'module:adyen/' . ltrim($templatePath, '/');
    }

    /**
     * @return string
     */
    public function getHttpHost()
    {
        return $this->httpHost;
    }
}
