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

use Adyen\PrestaShop\service\adapter\classes\Configuration;
use Adyen\PrestaShop\service\Checkout;
use Adyen\PrestaShop\service\CheckoutUtility;

class DataTest extends \PHPUnit_Framework_TestCase
{
    /** @var Data */
    private $adyenHelper;

    protected function setUp()
    {
        $originDomain = 'https://example.com';

        /** @var Configuration|\PHPUnit_Framework_MockObject_MockObject $configuration */
        $configuration = $this->getMockBuilder('Adyen\PrestaShop\service\adapter\classes\Configuration')
                              ->disableOriginalConstructor()
                              ->getMock();

        $configuration->sslEncryptionKey = 'adyen-prestashop-fake-key';
        $configuration->httpHost = $originDomain;

        /** @var CheckoutUtility|\PHPUnit_Framework_MockObject_MockObject $adyenCheckoutUtilityService */
        $adyenCheckoutUtilityService = $this->getMockBuilder('Adyen\PrestaShop\service\CheckoutUtility')
                                            ->disableOriginalConstructor()
                                            ->getMock();

        $adyenCheckoutUtilityService->method('originKeys')
                                    ->with(array("originDomains" => array($originDomain)))
                                    ->willReturn(array('originKeys' => array($originDomain => 'asdf')));

        /** @var Checkout|\PHPUnit_Framework_MockObject_MockObject $adyenCheckoutService */
        $adyenCheckoutService = $this->getMockBuilder('Adyen\PrestaShop\service\Checkout')
                                     ->disableOriginalConstructor()
                                     ->getMock();

        $logger = $this->getMockBuilder(\Adyen\PrestaShop\service\logger\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->adyenHelper = new Data(
            $configuration,
            $adyenCheckoutUtilityService,
            $adyenCheckoutService,
            $logger
        );
    }

    public function testGetOriginKeyForOrigin()
    {
        $this->assertInternalType('string', $this->adyenHelper->getOriginKeyForOrigin());
        $this->assertEquals('asdf', $this->adyenHelper->getOriginKeyForOrigin());
    }
}
