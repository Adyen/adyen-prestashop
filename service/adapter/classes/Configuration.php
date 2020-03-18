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

namespace Adyen\PrestaShop\service\adapter\classes;

use Adyen\Environment;
use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\service\Logger;

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
     * @var VersionChecker
     */
    private $versionChecker;

    /**
     * Configuration constructor.
     *
     * @param Logger $logger
     * @param VersionChecker $versionChecker
     */
    public function __construct(
        Logger $logger,
        VersionChecker $versionChecker
    )
    {
        $this->logger = $logger;
        $this->httpHost = \Tools::getHttpHost(true, true);
        $adyenModeConfiguration = \Configuration::get('ADYEN_MODE');
        $this->adyenMode = !empty($adyenModeConfiguration) ? $adyenModeConfiguration : Environment::TEST;
        $this->sslEncryptionKey = _COOKIE_KEY_;
        $this->encryptedApiKey = $this->getEncryptedAPIKey();
        $this->liveEndpointPrefix = \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
        $this->moduleVersion = '2.0.0';
        $this->moduleName = 'adyen-prestashop';
        $this->versionChecker = $versionChecker;
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
     * Supports default for both PrestaShop 1.6 and 1.7
     *
     * @param $key
     * @param null $id_lang
     * @param null $id_shop_group
     * @param null $id_shop
     * @param bool $default
     * @return bool
     */
    public function get($key, $id_lang = null, $id_shop_group = null, $id_shop = null, $default = false)
    {
        if ($this->versionChecker->isPrestaShop16()) {
            $configurationValue = \Configuration::get($key, $id_lang, $id_shop_group, $id_shop);
            if (false === $configurationValue && false !== $default) {
                return $default;
            }

            return $configurationValue;
        } else {
            return \Configuration::get($key, $id_lang, $id_shop_group, $id_shop, $default);
        }
    }
}
