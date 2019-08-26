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

    protected function setUp()
    {
        $sslEncryptionKey = 'adyen-prestashop-fake-key';
        $originDomain = 'https://example.com';

        /** @var CheckoutUtility|\PHPUnit_Framework_MockObject_MockObject $adyenCheckoutUtilityService */
        $adyenCheckoutUtilityService = $this->getMockBuilder(CheckoutUtility::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adyenCheckoutUtilityService->method('originKeys')
            ->with(["originDomains" => [$originDomain]])
            ->willReturn(['originKeys' => [$originDomain => 'asdf']]);

        $this->adyenHelper = new Data(
            $originDomain,
            ['mode' => \Adyen\Environment::TEST, 'apiKey' => 'ADYEN_APIKEY_TEST'],
            $sslEncryptionKey,
            $adyenCheckoutUtilityService
        );
    }

    public function testGetOriginKeyForOrigin()
    {
        $this->assertInternalType('string', $this->adyenHelper->getOriginKeyForOrigin());
        $this->assertEquals('asdf', $this->adyenHelper->getOriginKeyForOrigin());
    }
}
