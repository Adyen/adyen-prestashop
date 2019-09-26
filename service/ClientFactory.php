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

namespace Adyen\PrestaShop\service;

class ClientFactory
{
    /**
     * Initializes and returns Adyen Client and sets the required parameters of it.
     *
     * @param string $encryptedApiKey
     * @param string $liveEndpointUrlPrefix
     * @param string $environment
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    public function createDefaultClient($encryptedApiKey, $liveEndpointUrlPrefix, $environment)
    {
        $client = new \Adyen\Client();
        $client->setXApiKey($encryptedApiKey);
        $client->setAdyenPaymentSource(\Adyen\PrestaShop\helper\Configuration::MODULE_NAME, \Adyen\PrestaShop\helper\Configuration::VERSION);
        $client->setExternalPlatform("PrestaShop", _PS_VERSION_);
        $client->setEnvironment($environment, $liveEndpointUrlPrefix);
        return $client;
    }

    /**
     * Determines if PrestaShop is running in demo mode
     *
     * @param string $mode
     * @return bool
     */
    public function isDemoMode($mode)
    {
        if (strpos($mode, 'test') !== false) {
            return true;
        } else {
            return false;
        }
    }
}
