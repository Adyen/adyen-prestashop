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

class Client extends \Adyen\Client
{
    /**
     * @var adapter\classes\Configuration
     */
    private $configuration;

    public function __construct(\Adyen\PrestaShop\service\adapter\classes\Configuration $configuration)
    {
        parent::__construct();
        $this->setXApiKey($configuration->apiKey);
        $this->setAdyenPaymentSource(
            \Adyen\PrestaShop\service\Configuration::MODULE_NAME,
            \Adyen\PrestaShop\service\Configuration::VERSION
        );
        $this->setExternalPlatform("PrestaShop", _PS_VERSION_);
        $this->setEnvironment($configuration->adyenMode, $configuration->liveEndpointPrefix);

        $this->configuration = $configuration;
    }
}