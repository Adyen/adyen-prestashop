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
 * Adyen PrestaShop Module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service;


class CheckoutFactory
{
    /**
     * Creates a Checkout Utility Service with as little arguments as possible.
     *
     * @param string $apiKey
     * @param string $environment
     * @return \Adyen\Service\Checkout
     * @throws \Adyen\AdyenException
     */
    public function createDefaultCheckout($apiKey, $environment)
    {
        $clientFactory = new \Adyen\PrestaShop\service\ClientFactory();
        $adyenCheckoutService = new \Adyen\Service\Checkout(
            $clientFactory->createDefaultClient(
                $apiKey, \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX'), $environment
            )
        );
        return $adyenCheckoutService;
    }
}