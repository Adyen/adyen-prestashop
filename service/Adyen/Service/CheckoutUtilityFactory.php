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

namespace Adyen\PrestaShop\service\Adyen\Service;

class CheckoutUtilityFactory
{

    /**
     * Creates a Checkout Utility Service with as little arguments as possible.
     *
     * @param string $apiKey
     * @param string $environment
     * @return \Adyen\Service\CheckoutUtility
     * @throws \Adyen\AdyenException
     */
    public function createDefaultCheckoutUtility($apiKey, $environment)
    {
        $clientFactory = new \Adyen\PrestaShop\service\Adyen\ClientFactory();
        $adyenCheckoutUtilityService = new \Adyen\Service\CheckoutUtility(
            $clientFactory->createDefaultClient(
                $apiKey, \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX'), $environment
            )
        );
        return $adyenCheckoutUtilityService;
    }
}