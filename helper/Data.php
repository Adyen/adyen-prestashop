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

use Adyen;
use Adyen\AdyenException;
use Adyen\Service\CheckoutUtility;
use Adyen\Service\Checkout;

class Data
{
    /**
     * @var string
     */
    private $httpHost;

    /**
     * @var array
     */
    private $configuration;

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

    public function __construct(
        $httpHost,
        $configuration,
        $sslEncryptionKey,
        CheckoutUtility $adyenCheckoutUtilityService,
        Checkout $adyenCheckoutService
    ) {
        $this->httpHost = $httpHost;
        $this->configuration = $configuration;
        $this->sslEncryptionKey = $sslEncryptionKey;
        $this->adyenCheckoutUtilityService = $adyenCheckoutUtilityService;
        $this->adyenCheckoutService = $adyenCheckoutService;
    }

    /**
     * @return mixed
     */
    public function getOrigin()
    {
        return $this->httpHost;
    }

    /**
     * Get origin key for a specific origin using the adyen api library client
     *
     * @param $origin
     * @param int|null $storeId
     * @return string
     */
    public function getOriginKeyForOrigin()
    {

        $origin = $this->getOrigin();

        $params = array("originDomains" => array($origin));

        try {
            $response = $this->adyenCheckoutUtilityService->originKeys($params);
        } catch (AdyenException $e) {
            $this->adyenLogger()->logError("exception: " . $e->getMessage());
        }

        $originKey = "";

        // TODO: improve error treatment
        if (!empty($response['originKeys'][$origin])) {
            $originKey = $response['originKeys'][$origin];
        } else {
            $this->adyenLogger()->logError("OriginKey is empty, please verify that your API key is correct");
        }

        return $originKey;
    }

    /**
     * @param $store
     * @param $country
     * @return array
     */
    public function fetchPaymentMethods($countryCode, $amount, $currency, $shopperReference, $shopperLocale)
    {
        $merchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');

        if (!$merchantAccount) {
            return [];
        }

        $adyFields = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $countryCode,
            "amount" => [
                "currency" => $currency,
                "value" => $this->formatAmount(
                    $amount,
                    $currency
                ),
            ],
            "shopperReference" => $shopperReference,
            "shopperLocale" => $shopperLocale
        ];

        try {
            $responseData = $this->adyenCheckoutService->paymentMethods($adyFields);
        } catch (\Adyen\AdyenException $e) {
            $this->helperData->adyenLogger()->logError("There was an error retrieving the payment methods. message: " . $e->getMessage());
        }
        return $responseData;
    }


    public function isDemoMode()
    {
        if (strpos($this->configuration['mode'], \Adyen\Environment::TEST) !== false) {
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

    /**
     * Initializes and returns Adyen Client and sets the required parameters of it
     *
     * @return \Adyen\Client
     * @throws AdyenException
     */
    public function initializeAdyenClient()
    {
        $apiKey = $this->getAPIKey();
        $client = $this->createAdyenClient();
        $client->setApplicationName("Prestashop plugin");
        $client->setXApiKey($apiKey);
        $client->setAdyenPaymentSource(\Adyen\PrestaShop\service\Configuration::MODULE_NAME, \Adyen\PrestaShop\service\Configuration::VERSION);
        $client->setExternalPlatform("Prestashop", _PS_VERSION_);

        if ($this->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE, \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX'));
        }
        return $client;
    }

    /**
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    private function createAdyenClient()
    {
        return new \Adyen\Client();
    }

    /**
     * Retrieve the API key
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAPIKey()
    {
        return $this->configuration['apiKey'];
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

    public function decrypt($data)
    {
        // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
        list($data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($data, 'aes-256-ctr', $this->sslEncryptionKey, 0, $iv);
    }

    /**
     * Determine if PrestaShop is 1.6
     * @return bool
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
     * @return int
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
    public function getLocale($context)
    {
        // no locale in Prestashop1.6 only languageCode that is en-en but we need en_EN
        if ($this->isPrestashop16()) {
            return $context->language->iso_code;
        } else {
            return $context->language->locale;
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
}