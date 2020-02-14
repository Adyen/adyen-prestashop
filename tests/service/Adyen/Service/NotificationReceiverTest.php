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
 * @copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\notification;

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\Util\HmacSignature;
use Mockery as m;
use PHPUnit\Framework\TestCase;

function pSQL($string)
{
    /** @noinspection PhpUndefinedMethodInspection */
    return NotificationReceiverTest::$functions->pSQL($string);
}

class NotificationReceiverTest extends TestCase
{
    /**
     * @var m\MockInterface
     */
    public static $functions;

    /**
     * @var Adyen\PrestaShop\service\Logger|\PHPUnit_Framework_MockObject_MockObject $logger
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

        $this->logger = $this->getMockBuilder('Adyen\PrestaShop\service\Logger')
                             ->disableOriginalConstructor()
                             ->getMock();
        $this->logger->method('error');

        $this->adyenHelper = $this->getMockBuilder('Adyen\PrestaShop\helper\Data')
                                  ->disableOriginalConstructor()
                                  ->getMock();

        $this->hmacSignature = $this->getMock('Adyen\Util\HmacSignature');

        $this->dbInstance = $this->getMockBuilder('Db')
                                 ->setMethods(array('getValue', 'insert'))
                                 ->disableOriginalConstructor()
                                 ->getMock();
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

        $notificationReceiver = new NotificationReceiver(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            'Merchant',
            'username',
            'password',
            $this->dbInstance,
            $this->logger
        );

        $this->setExpectedException('Adyen\PrestaShop\service\notification\HMACKeyValidationException');

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationReceiver->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/regular-notification.json'), true)
        );
    }

    public function testInvalidCredentialsThrowsException()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);

        $notificationReceiver = new NotificationReceiver(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            'Merchant',
            'username',
            'password',
            $this->dbInstance,
            $this->logger
        );

        $this->setExpectedException('Adyen\PrestaShop\service\notification\AuthenticationException');

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationReceiver->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/test-notification.json'), true)
        );
    }

    public function testInvalidMerchantConfigurationThrowsException()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);

        $notificationReceiver = new NotificationReceiver(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            '',
            'username',
            'password',
            $this->dbInstance,
            $this->logger
        );

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $this->setExpectedException('Adyen\PrestaShop\service\notification\MerchantAccountCodeException');

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationReceiver->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/invalid-merchant-test-notification.json'), true)
        );
    }

    public function testInvalidMerchantConfigurationThrowsAuthorizationExceptionForNonTestNotification()
    {
        $this->adyenHelper->method('isDemoMode')->willReturn(true);

        $notificationReceiver = new NotificationReceiver(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            '',
            'username',
            'password',
            $this->dbInstance,
            $this->logger
        );

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $this->setExpectedException('Adyen\PrestaShop\service\notification\AuthorizationException');

        /** @noinspection PhpUnhandledExceptionInspection */
        $notificationReceiver->doPostProcess(
            json_decode(file_get_contents(__DIR__ . '/invalid-merchant-notification.json'), true)
        );
    }

    public function testNotificationIsInsertedWithProperData()
    {
        $notificationItems = json_decode(file_get_contents(__DIR__ . '/regular-notification.json'), true);
        $notificationRequestItem = $notificationItems['notificationItems'][0]['NotificationRequestItem'];
        define('_DB_PREFIX_', 'ps_');

        self::$functions->shouldReceive('pSQL')->andReturnUsing(function ($string) {
            $search = array('\\', "\0", "\n", "\r", "\x1a", "'", '"');
            $replace = array('\\\\', '\\0', '\\n', '\\r', "\Z", "\'", '\"');

            return str_replace($search, $replace, $string);
        });

        $this->adyenHelper->method('isDemoMode')->willReturn(true);
        $this->hmacSignature->method('isValidNotificationHMAC')->willReturn(true);

        $this->dbInstance->method('insert')->with(
            'adyen_notification',
            $this->callback(function ($subject) use ($notificationRequestItem) {
                $arr = array(
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
                );
                return $arr == $subject;
            })
        );

        $_SERVER['PHP_AUTH_USER'] = 'username';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $notificationReceiver = new NotificationReceiver(
            $this->adyenHelper,
            $this->hmacSignature,
            'hmac',
            'Merchant',
            'username',
            'password',
            $this->dbInstance,
            $this->logger
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->assertEquals('[accepted]', $notificationReceiver->doPostProcess($notificationItems));
    }
}
