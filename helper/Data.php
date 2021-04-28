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
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\helper;

use Address;
use Adyen;
use Adyen\AdyenException;
use Adyen\Environment;
use Adyen\PrestaShop\infra\Crypto;
use Adyen\PrestaShop\service\adapter\classes\Configuration;
use Adyen\PrestaShop\service\adapter\classes\Language;
use Adyen\PrestaShop\service\Checkout;
use Adyen\PrestaShop\service\Logger;
use Cart;
use Country;
use Currency;
use Db;
use Exception;
use FileLogger;
use Tools;

class Data
{
    /**
     * @var string
     */
    private $sslEncryptionKey;

    /**
     * @var Checkout
     */
    private $adyenCheckoutService;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Language
     */
    private $languageAdapter;

    /**
     * @var Crypto
     */
    private $crypto;

    /**
     * Data constructor.
     *
     * @param Configuration $configuration
     * @param Checkout $adyenCheckoutService
     * @param Logger $logger
     * @param Language $languageAdapter
     * @param Crypto $crypto
     */
    public function __construct(
        Configuration $configuration,
        Checkout $adyenCheckoutService,
        Logger $logger,
        Language $languageAdapter,
        Crypto $crypto
    ) {
        $this->sslEncryptionKey = $configuration->sslEncryptionKey;
        $this->adyenCheckoutService = $adyenCheckoutService;
        $this->configuration = $configuration;
        $this->logger = $logger;
        $this->languageAdapter = $languageAdapter;
        $this->crypto = $crypto;
    }

    /**
     * @param Cart $cart
     * @param $language
     *
     * @return array
     * @throws Exception
     */
    public function fetchPaymentMethods(Cart $cart, $language)
    {
        $merchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');
        $customer = new \Customer($cart->id_customer);

        if (!$merchantAccount) {
            $this->logger->error(
                "The merchant account field is empty, check your Adyen configuration in Prestashop."
            );

            return array();
        }

        if (!\Validate::isLoadedObject($customer)) {
            $this->logger->error(
                sprintf('Unable to load customer linked to Cart (%s)', $cart->id)
            );

            return array();
        }

        $amount = $cart->getOrderTotal();
        $currencyData = Currency::getCurrency($cart->id_currency);
        $currency = $currencyData['iso_code'];
        $address = new Address($cart->id_address_invoice);
        $countryCode = Country::getIsoById($address->id_country);
        $shopperLocale = $this->languageAdapter->getLocaleCode($language);

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
            "shopperLocale" => $shopperLocale
        );

        // If customer is a guest, do not send shopperReference
        if (!$customer->isGuest()) {
            $shopperReference = str_pad($cart->id_customer, 3, '0', STR_PAD_LEFT);
            $adyenFields['shopperReference'] = $shopperReference;
        }

        $responseData = "";
        try {
            $responseData = $this->adyenCheckoutService->paymentMethods($adyenFields);
        } catch (\Exception $e) {
            $this->logger->error("There was an error retrieving the payment methods. message: " . $e->getMessage());
        }
        return $responseData;
    }

    /**
     * @return bool
     * @deprecated Use Adyen\PrestaShop\service\adapter\classes\Configuration isTestMode() instead.
     * This method will be removed in version 2.
     */
    public function isDemoMode()
    {
        if (strpos($this->configuration->adyenMode, Environment::TEST) !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return FileLogger
     * @deprecated Use \Adyen\PrestaShop\service\logger instead. This method will be removed in version 2.
     */
    public function adyenLogger()
    {
        // TODO: debug level should be in configuration
        $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.

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
            case 'action':
                $response = array(
                    'action' => 'action',
                    'response' => $details['response']
                );

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
            default:
                $response = array(
                    'action' => 'error',
                    'message' => 'Something went wrong'
                );
                break;
        }

        return Tools::jsonEncode($response);
    }

    /**
     * Return the formatted currency. Adyen accepts the currency in multiple formats.
     * @param $amount
     * @param $currency
     * @return string
     * @deprecated Use Adyen\Util\Currency sanitize() from Adyen php api library
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
     * Get locale for 1.6/1.7
     * @return mixed
     * @deprecated use Adyen\PrestaShop\service\adapter\classes\Language
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

        return 'module:adyenofficial/' . ltrim($templatePath, '/');
    }

    /**
     * Execute basic SELECT statement. If table does not exist, catch exception and return false
     *
     * @param $tableName
     * @return bool
     *
     */
    public function tableExists($tableName)
    {
        $db = Db::getInstance();
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . $tableName;
        try {
            $db->getValue($sql);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Table %s does not exist', _DB_PREFIX_ . $tableName));
            return false;
        }

        return true;
    }
}
