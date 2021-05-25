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
 * @copyright (c) 2021 Adyen B.V.
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
    public $clientKey;

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
     * @var string
     */
    public $integratorName;

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
        $this->encryptedApiKey = $this->getEncryptedAPIKey();
        $this->clientKey = $this->getClientKey();
        $this->liveEndpointPrefix = \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
        $this->moduleVersion = '3.6.0';
        $this->moduleName = 'adyen-prestashop';
        $this->integratorName = \Configuration::get('ADYEN_INTEGRATOR_NAME', null, null, null, "");
    }

    /**
     * Retrieves the encrypted API key based on the mode set in the admin configurations
     *
     * @return string
     */
    private function getEncryptedAPIKey()
    {
        if ($this->isTestMode()) {
            $encryptedApiKey = \Configuration::get('ADYEN_APIKEY_TEST');
        } else {
            $encryptedApiKey = \Configuration::get('ADYEN_APIKEY_LIVE');
        }
        return $encryptedApiKey;
    }

    /**
     * Retrieves the Client key based on the mode set in the admin configurations
     *
     * @return string
     */
    private function getClientKey()
    {
        if ($this->isTestMode()) {
            $clientKey = \Configuration::get('ADYEN_CLIENTKEY_TEST');
        } else {
            $clientKey = \Configuration::get('ADYEN_CLIENTKEY_LIVE');
        }
        return $clientKey;
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

    /**
     * @return bool
     */
    public function isAutoCronjobRunnerEnabled()
    {
        return !!\Configuration::get('ADYEN_AUTO_CRON_JOB_RUNNER');
    }
}
