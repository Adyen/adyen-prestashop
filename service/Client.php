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
 *
 * Copyright (c) 2020 Adyen B.V.
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

    /**
     * Client constructor.
     *
     * @param adapter\classes\Configuration $configuration
     * @param Logger $logger
     *
     * @throws \Adyen\AdyenException
     */
    public function __construct(
        \Adyen\PrestaShop\service\adapter\classes\Configuration $configuration,
        Logger $logger
    ) {
        parent::__construct();
        $this->setXApiKey($configuration->apiKey);
        $this->setAdyenPaymentSource(Configuration::MODULE_NAME, $configuration->moduleVersion);
        $this->setMerchantApplication(Configuration::MODULE_NAME, $configuration->moduleVersion);
        $this->setExternalPlatform("PrestaShop", _PS_VERSION_);
        $this->setEnvironment($configuration->adyenMode, $configuration->liveEndpointPrefix);

        $this->setLogger($logger);

        $this->configuration = $configuration;
    }
}