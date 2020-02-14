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
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\adapter\classes;

use Adyen\Environment;
use Tools;

class Configuration
{
    /**
     * @var string
     */
    public $httpHost;

    /**
     * @var string
     */
    public $adyenMode;

    /**
     * @var string
     */
    public $sslEncryptionKey;

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var string
     */
    public $liveEndpointPrefix;

    /**
     * @var string
     */
    public $moduleVersion;

    /**
     * Configuration constructor.
     */
    public function __construct()
    {
        $this->httpHost = Tools::getHttpHost(true, true);
        $adyenModeConfiguration = \Configuration::get('ADYEN_MODE');
        $this->adyenMode = !empty($adyenModeConfiguration) ? $adyenModeConfiguration : Environment::TEST;;
        $this->sslEncryptionKey = _COOKIE_KEY_;
        $this->apiKey = $this->getAPIKey($this->adyenMode, $this->sslEncryptionKey);
        $this->liveEndpointPrefix = \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
        $this->moduleVersion = '1.2.0';
    }

    /**
     * Retrieves the API key
     *
     * @param string $adyenRunningMode
     * @param $password
     *
     * @return string
     */
    private function getAPIKey($adyenRunningMode, $password)
    {
        if ($this->isTestMode($adyenRunningMode)) {
            $apiKey = $this->decrypt(\Configuration::get('ADYEN_APIKEY_TEST'), $password);
        } else {
            $apiKey = $this->decrypt(\Configuration::get('ADYEN_APIKEY_LIVE'), $password);
        }
        return $apiKey;
    }

    /**
     * Checks if plug-in is running in test mode or not
     *
     * @param $adyenRunningMode
     *
     * @return bool
     */
    private function isTestMode($adyenRunningMode)
    {
        if (strpos($adyenRunningMode, Environment::TEST) !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Decrypts data
     *
     * @param $data
     * @param $password
     *
     * @return string
     */
    private function decrypt($data, $password)
    {
        if (!$data) {
            return '';
        }
        // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
        list($data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($data, 'aes-256-ctr', $password, 0, $iv);
    }
}