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

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\service\notification\AuthenticationException;
use Adyen\PrestaShop\service\notification\AuthorizationException;
use Adyen\PrestaShop\service\notification\HMACKeyValidationException;
use Adyen\PrestaShop\service\notification\MerchantAccountCodeException;
use Db;
use Mockery as m;

function pSQL($string)
{
    /** @noinspection PhpUndefinedMethodInspection */
    return CronControllerTest::$functions->pSQL($string);
}

class CronControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var m\MockInterface
     */
    public static $functions;

    /**
     * @var \FileLogger|\PHPUnit_Framework_MockObject_MockObject $logger
     */
    private $logger;

    /**
     * @var AdyenHelper|\PHPUnit_Framework_MockObject_MockObject $adyenHelper
     */
    private $adyenHelper;

    /**
     * @var Db|\PHPUnit_Framework_MockObject_MockObject $dbInstance
     */
    private $dbInstance;

    /**
     *
     */
    protected function setUp()
    {
        self::$functions = m::mock();

        $this->logger = $this->getMockBuilder(\FileLogger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger->method('logError');

        $this->adyenHelper = $this->getMockBuilder(AdyenHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenHelper->method('adyenLogger')->willReturn($this->logger);

        $this->dbInstance = $this->getMockBuilder(Db::class)->disableOriginalConstructor()->getMock();
    }

    public function tearDown()
    {
        // see Mockery's documentation for why we do this
        m::close();
    }

    public function testNotificationIsProcessedAndMessageAdded()
    {
        $notification = json_decode(file_get_contents(__DIR__ . '/unprocessed-notification.json'), true);

        $this->dbInstance->method('insert')->with(
            _DB_PREFIX_ . 'adyen_notification',
            $this->callback(function ($subject) use ($notification) {
                $arr = [
                    'pspreference' => $notification['pspReference'],
                    'merchant_reference' => $notification['merchantReference'],
                    'event_code' => $notification['eventCode'],
                    'success' => $notification['success'],
                    'payment_method' => $notification['paymentMethod'],
                    'amount_value' => $notification['amount']['value'],
                    'amount_currency' => $notification['amount']['currency'],
                    'reason' => $notification['reason'],
                    'additional_data' => pSQL(serialize($notification['additionalData'])),
                    'done' => $notification['done'],
                    'processing' => $notification['processing'],
                    'created_at' => $notification['created_at'],
                    'updated_at' => $notification['updated_at']
                ];
                return $arr == $subject;
            }),
            false,
            false,
            Db::INSERT,
            false
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        //$this->assertEquals(true, $notificationReceiver->doPostProcess($notificationItems));

        //todo finish test
    }
}
