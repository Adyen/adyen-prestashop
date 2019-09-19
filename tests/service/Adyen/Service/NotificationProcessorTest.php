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

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\service\notification\AuthenticationException;
use Adyen\PrestaShop\service\notification\AuthorizationException;
use Adyen\PrestaShop\service\notification\HMACKeyValidationException;
use Adyen\PrestaShop\service\notification\MerchantAccountCodeException;
use Adyen\Util\HmacSignature;
use Db;
use Mockery as m;

function pSQL($string)
{
    /** @noinspection PhpUndefinedMethodInspection */
    return NotificationProcessorTest::$functions->pSQL($string);
}

class NotificationProcessorTest extends \PHPUnit_Framework_TestCase
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
     * @var HmacSignature|\PHPUnit_Framework_MockObject_MockObject $hmacSignature
     */
    private $hmacSignature;

    /**
     * @var Db|\PHPUnit_Framework_MockObject_MockObject $dbInstance
     */
    private $dbInstance;

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

        $this->hmacSignature = $this->getMock(HmacSignature::class);

        $this->dbInstance = $this->getMockBuilder(Db::class)->disableOriginalConstructor()->getMock();
    }

    public function tearDown()
    {
        // see Mockery's documentation for why we do this
        m::close();
    }

    public function testInvalidHMACThrowsException()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);
        $this->hmacSignature->method('isValidNotificationHMAC')->willReturn(false);

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            'Merchant',
            'username',
            'password',
            $this->dbInstance
        );

        $this->setExpectedException(HMACKeyValidationException::class);

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationProcessor->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/regular-notification.json'), true)
        );
    }

    public function testInvalidCredentialsThrowsException()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            'Merchant',
            'username',
            'password',
            $this->dbInstance
        );

        $this->setExpectedException(AuthenticationException::class);

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationProcessor->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/test-notification.json'), true)
        );
    }

    public function testInvalidMerchantConfigurationThrowsException()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            '',
            'username',
            'password',
            $this->dbInstance
        );

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $this->setExpectedException(MerchantAccountCodeException::class);

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationProcessor->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/invalid-merchant-test-notification.json'), true)
        );
    }

    public function testInvalidMerchantConfigurationThrowsAuthorizationExceptionForNonTestNotification()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            '',
            'username',
            'password',
            $this->dbInstance
        );

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $this->setExpectedException(AuthorizationException::class);

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationProcessor->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/invalid-merchant-notification.json'), true)
        );
    }

    public function testNotificationIsInsertedWithProperData()
    {
        $notificationItems = json_decode(file_get_contents(__DIR__ . '/regular-notification.json'), true);
        $notificationRequestItem = $notificationItems['notificationItems'][0]['NotificationRequestItem'];

        self::$functions->shouldReceive('pSQL')->andReturnUsing(function ($string) {
            $search = array('\\', "\0", "\n", "\r", "\x1a", "'", '"');
            $replace = array('\\\\', '\\0', '\\n', '\\r', "\Z", "\'", '\"');

            return str_replace($search, $replace, $string);
        });

        $this->adyenHelper->method('isDemoMode')->willReturn(true);
        $this->hmacSignature->method('isValidNotificationHMAC')->willReturn(true);

        $this->dbInstance->method('insert')->with(
            _DB_PREFIX_ . 'adyen_notification',
            $this->callback(function ($subject) use ($notificationRequestItem) {
                $arr = [
                    'pspreference' => $notificationRequestItem['pspReference'],
                    'merchant_reference' => $notificationRequestItem['merchantReference'],
                    'event_code' => $notificationRequestItem['eventCode'],
                    'success' => $notificationRequestItem['success'],
                    'payment_method' => $notificationRequestItem['paymentMethod'],
                    'amount_value' => $notificationRequestItem['amount']['value'],
                    'amount_currency' => $notificationRequestItem['amount']['currency'],
                    'reason' => $notificationRequestItem['reason'],
                    'additional_data' => pSQL(serialize($notificationRequestItem['additionalData'])),
                    'created_at' => $subject['created_at'],
                    'updated_at' => $subject['updated_at']
                ];
                return $arr == $subject;
            }),
            false,
            false,
            Db::INSERT,
            false
        );

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            'Merchant',
            'username',
            'password',
            $this->dbInstance
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->assertEquals('[accepted]', $notificationProcessor->doPostProcess($notificationItems));
    }
}
