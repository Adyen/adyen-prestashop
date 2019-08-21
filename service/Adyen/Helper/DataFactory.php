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

namespace Adyen\PrestaShop\service\Adyen\Helper;


use Adyen\PrestaShop\service\Adyen\Service\CheckoutUtilityFactory;

class DataFactory
{
    /**
     * Creates an Adyen Helper object with as little arguments as possible.
     *
     * @param $adyenRunningMode
     * @param $sslEncryptionKey
     * @return \Adyen\PrestaShop\helper\Data
     * @throws \Adyen\AdyenException
     */
    public function createAdyenHelperData($adyenRunningMode, $sslEncryptionKey)
    {
        $checkoutUtilityFactory = new CheckoutUtilityFactory();
        return new \Adyen\PrestaShop\helper\Data(
            ['Tools', 'getHttpHost'],
            ['Configuration', 'get'],
            $sslEncryptionKey,
            $checkoutUtilityFactory->createDefaultCheckoutUtility(
                $this->getAPIKey($adyenRunningMode, $sslEncryptionKey), \Adyen\Environment::TEST
            )
        );
    }

    /**
     * Retrieves the API key
     *
     * @param string $adyenRunningMode
     * @param $password
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
     * @return bool
     */
    private function isTestMode($adyenRunningMode)
    {
        if (strpos($adyenRunningMode, \Adyen\Environment::TEST) !== false) {
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
     * @return string
     */
    private function decrypt($data, $password)
    {
        // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
        list($data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($data, 'aes-256-ctr', $password, 0, $iv);
    }

}
