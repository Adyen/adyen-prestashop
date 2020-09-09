<?php
/*
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

namespace Adyen\PrestaShop\service\adapter\classes;

use Adyen\Environment;
use Adyen\PrestaShop\service\Logger;
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
    public $encryptedApiKey;

    /**
     * @var string
     */
    public $liveEndpointPrefix;

    /**
     * @var string
     */
    public $moduleVersion;

    /**
     * @var string
     */
    public $moduleName;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Configuration constructor.
     *
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->httpHost = Tools::getHttpHost(true, true);
        $adyenModeConfiguration = \Configuration::get('ADYEN_MODE');
        $this->adyenMode = !empty($adyenModeConfiguration) ? $adyenModeConfiguration : Environment::TEST;
        $this->sslEncryptionKey = _COOKIE_KEY_;
        $this->encryptedApiKey = $this->getEncryptedAPIKey($this->adyenMode);
        $this->liveEndpointPrefix = \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
        $this->moduleVersion = '2.1.7';
        $this->moduleName = 'adyen-prestashop';
    }

    /**
     * Retrieves the encrypted API key based on the mode set in the admin configurations
     *
     * @param string $adyenRunningMode
     * @return string
     */
    private function getEncryptedAPIKey($adyenRunningMode)
    {
        if ($this->isTestMode($adyenRunningMode)) {
            $encryptedApiKey = \Configuration::get('ADYEN_APIKEY_TEST');
        } else {
            $encryptedApiKey = \Configuration::get('ADYEN_APIKEY_LIVE');
        }
        return $encryptedApiKey;
    }

    /**
     * Checks if plug-in is running in test mode or not
     *
     * @return bool
     */
    public function isTestMode()
    {
        if (strpos($this->adyenMode, Environment::TEST) !== false) {
            return true;
        } else {
            return false;
        }
    }
}
