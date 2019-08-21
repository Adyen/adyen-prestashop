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

namespace Adyen\PrestaShop\helper;

use Adyen\Service\CheckoutUtility;

class DataTest extends \PHPUnit_Framework_TestCase
{
    /** @var Data */
    private $adyenHelper;
    /** @var string */
    private $sslEncryptionKey;

    protected function setUp()
    {
        $this->sslEncryptionKey = 'adyen-prestashop-fake-key';
        $originDomain = 'https://example.com';

        /** @var CheckoutUtility|\PHPUnit_Framework_MockObject_MockObject $adyenCheckoutUtilityService */
        $adyenCheckoutUtilityService = $this->getMockBuilder(CheckoutUtility::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adyenCheckoutUtilityService->method('originKeys')
            ->with(["originDomains" => [$originDomain]])
            ->willReturn(['originKeys' => [$originDomain => 'asdf']]);

        $this->adyenHelper = new Data(
            function () use ($originDomain) {
                return $originDomain;
            }, $this->getConfigurationKeyClosure(), $this->sslEncryptionKey, $adyenCheckoutUtilityService
        );
    }

    public function testGetOriginKeyForOrigin()
    {
        $this->assertInternalType('string', $this->adyenHelper->getOriginKeyForOrigin());
        $this->assertEquals('asdf', $this->adyenHelper->getOriginKeyForOrigin());
    }

    /**
     * @return \Closure
     */
    protected function getConfigurationKeyClosure()
    {
        return function ($key) {
            if ($key == 'ADYEN_APIKEY_TEST' || $key == 'ADYEN_APIKEY_LIVE') {
                //openssl_decrypt($data, 'aes-256-ctr', _COOKIE_KEY_, 0, $iv);
                $cipher = 'aes-256-ctr';
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
                return base64_encode(
                    openssl_encrypt($key, $cipher, $this->sslEncryptionKey, 0, $iv) . '::' . $iv
                );
            }
            if ($key == 'ADYEN_MODE') {
                return 'test';
            }
        };
    }
}
