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

namespace Adyen\PrestaShop\service;

use Adyen\PrestaShop\exception\GenericLoggedException;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\infra\Crypto;

class Client extends \Adyen\Client
{
    /**
     * @var adapter\classes\Configuration
     */
    private $configuration;

    /**
     * Client constructor.
     *
     * @param adapter\classes\Configuration $configuration
     * @param Logger $logger
     * @param Crypto $crypto
     * @throws \Adyen\AdyenException
     */
    public function __construct(
        \Adyen\PrestaShop\service\adapter\classes\Configuration $configuration,
        Logger $logger,
        Crypto $crypto
    ) {
        parent::__construct();

        $apiKey = '';

        try {
            $apiKey = $crypto->decrypt($configuration->apiKey);
        } catch (GenericLoggedException $e) {
            $logger->error('For configuration "ADYEN_CRONJOB_TOKEN" an exception was thrown: ' . $e->getMessage());
        } catch (MissingDataException $e) {
            $logger->debug('The API key configuration value is missing');
        }

        $this->setXApiKey($apiKey);
        $this->setAdyenPaymentSource(Configuration::MODULE_NAME, $configuration->moduleVersion);
        $this->setMerchantApplication(Configuration::MODULE_NAME, $configuration->moduleVersion);
        $this->setExternalPlatform("PrestaShop", _PS_VERSION_);
        $this->setEnvironment($configuration->adyenMode, $configuration->liveEndpointPrefix);

        $this->setLogger($logger);

        $this->configuration = $configuration;
    }
}
