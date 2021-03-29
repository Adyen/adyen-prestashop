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
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service;

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\model\AdyenPaymentResponse;
use Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\notification\NotificationProcessor;
use Adyen\Util\Currency;
use Context;
use Db;
use Mockery as m;
use Order;
use PHPUnit_Framework_MockObject_MockObject;
use PrestaShopDatabaseException;
use PrestaShopException;
use Adyen\PrestaShop\service\Order as OrderService;

class NotificationProcessorTest extends \PHPUnit_Framework_TestCase
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
     * @var AdyenHelper|PHPUnit_Framework_MockObject_MockObject $adyenHelper
     */
    private $adyenHelper;

    /**
     * @var Db|PHPUnit_Framework_MockObject_MockObject $dbInstance
     */
    private $dbInstance;

    /**
     * @var CustomerThreadAdapter|PHPUnit_Framework_MockObject_MockObject $customerThreadAdapter
     */
    private $customerThreadAdapter;

    /**
     * @var AdyenPaymentResponse|PHPUnit_Framework_MockObject_MockObject $adyenPaymentResponseMock
     */
    private $adyenPaymentResponseMock;

    /**
     * @var OrderService|PHPUnit_Framework_MockObject_MockObject $orderServiceMock
     */
    private $orderServiceMock;

    /**
     * @var Currency|PHPUnit_Framework_MockObject_MockObject $utilCurrency
     */
    private $utilCurrency;

    /**
     * @var OrderPaymentService|PHPUnit_Framework_MockObject_MockObject $orderPaymentService
     */
    private $orderPaymentService;

    /**
     *
     */
    protected function setUp()
    {
        self::$functions = m::mock();


        $this->logger = $this->getMockBuilder(\Adyen\PrestaShop\service\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->adyenHelper = $this->getMockBuilder(AdyenHelper::class)
                                  ->disableOriginalConstructor()
                                  ->getMock();

        $this->dbInstance = $this->getMockBuilder(Db::class)->disableOriginalConstructor()->getMock();

        // Mock customerThread
        $customerThreadMock = $this->getMockBuilder(\CustomerThread::class)
                                   ->disableOriginalConstructor()
                                   ->getMock();
        $customerThreadMock->id = 1;

        // Mock customerThreadAdapter
        $this->customerThreadAdapter = $this->getMockBuilder(CustomerThreadAdapter::class)
                                            ->disableOriginalConstructor()
                                            ->getMock();
        $this->customerThreadAdapter->method('getCustomerThreadByEmailAndOrderId')
                                    ->willReturn($customerThreadMock);

        // Mock AdyenNotification
        /** @var PHPUnit_Framework_MockObject_MockObject|AdyenPaymentResponse $customerMock */
        $this->adyenPaymentResponseMock = $this->getMockBuilder(AdyenPaymentResponse::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderServiceMock = $this->getMockBuilder(OrderService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->utilCurrency = new Currency();
        $this->orderPaymentService = new OrderPaymentService();
    }

    protected function tearDown()
    {
        // see Mockery's documentation for why we do this
        m::close();
    }

    public function testIsMessageAddedSuccessful()
    {
        $notification = json_decode(file_get_contents(__DIR__ . '/unprocessed-notification.json'), true);

        // Mock Customer with email
        /** @var PHPUnit_Framework_MockObject_MockObject|\Customer $customerMock */
        $customerMock = $this->getMockBuilder(\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customerMock->email = 'test@test.com';

        // Mock order
        /** @var PHPUnit_Framework_MockObject_MockObject|Order $orderMock */
        $orderMock = $this->getMockBuilder(\Order::class)
                          ->setMethods(array('getCustomer'))
                          ->disableOriginalConstructor()
                          ->getMock();

        $orderMock->method('getCustomer')->willReturn($customerMock);
        $orderMock->id = 1;
        $orderMock->id_customer = 1;

        // Mock order adapter
        /** @var PHPUnit_Framework_MockObject_MockObject|OrderAdapter $orderAdapter */
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)->disableOriginalConstructor()->getMock();
        $orderAdapter->method('getOrderByCartId')->willReturn($orderMock);

        // Mock customerMessage add
        $customerMessageMock = m::mock('overload:CustomerMessage');
        $customerMessageMock->shouldReceive('add')
                            ->once()
                            ->andReturn(true);

        /** @var PHPUnit_Framework_MockObject_MockObject|Context $context */
        $context = $this->getMockBuilder('Context')->disableOriginalConstructor()->getMock();
        $shop = new \stdClass();
        $shop->id = 1;
        $context->shop = $shop;
        $language = new \stdClass();
        $language->id = 1;
        $context->language = $language;

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->dbInstance,
            $orderAdapter,
            $this->customerThreadAdapter,
            $this->logger,
            $context,
            $this->adyenPaymentResponseMock,
            $this->orderServiceMock,
            $this->utilCurrency,
            $this->orderPaymentService
        );

        $this->assertTrue($notificationProcessor->addMessage($notification));
    }

    public function testIsMessageAddedOrderDoesNotExist()
    {
        $notification = json_decode(file_get_contents(__DIR__ . '/unprocessed-notification.json'), true);

        // Mock order adapter
        /** @var PHPUnit_Framework_MockObject_MockObject|OrderAdapter $orderAdapter */
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)->disableOriginalConstructor()->getMock();
        $orderAdapter->method('getOrderByCartId')->willReturn(null);

        /** @var PHPUnit_Framework_MockObject_MockObject|Context $context */
        $context = $this->getMockBuilder('Context')->disableOriginalConstructor()->getMock();
        $shop = new \stdClass();
        $shop->id = 1;
        $context->shop = $shop;
        $language = new \stdClass();
        $language->id = 1;
        $context->language = $language;

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->dbInstance,
            $orderAdapter,
            $this->customerThreadAdapter,
            $this->logger,
            $context,
            $this->adyenPaymentResponseMock,
            $this->orderServiceMock,
            $this->utilCurrency,
            $this->orderPaymentService
        );

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Order with id: "0" cannot be found while notification with id: "1" was processed.');

        $this->assertFalse($notificationProcessor->addMessage($notification));
    }

    public function testIsMessageAddedCustomerDoesNotExist()
    {
        $notification = json_decode(file_get_contents(__DIR__ . '/unprocessed-notification.json'), true);

        // Mock order
        /** @var PHPUnit_Framework_MockObject_MockObject|Order $orderMock */
        $orderMock = $this->getMockBuilder(Order::class)
                          ->setMethods(array('getCustomer'))
                          ->disableOriginalConstructor()
                          ->getMock();

        $orderMock->method('getCustomer')->willReturn(null);
        $orderMock->id = '';
        $orderMock->id_customer = null;

        // Mock order adapter
        /** @var PHPUnit_Framework_MockObject_MockObject|OrderAdapter $orderAdapter */
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)->disableOriginalConstructor()->getMock();
        $orderAdapter->method('getOrderByCartId')
                     ->willReturn($orderMock);

        /** @var Context $context */
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->dbInstance,
            $orderAdapter,
            $this->customerThreadAdapter,
            $this->logger,
            $context,
            $this->adyenPaymentResponseMock,
            $this->orderServiceMock,
            $this->utilCurrency,
            $this->orderPaymentService
        );

        $this->logger->expects($this->once())
                     ->method('error')
                     ->with(
                         'Customer with id: "" cannot be found for order with id: "" while notification with' .
                         ' id: "1" was processed.'
                     );

        try {
            $this->assertFalse($notificationProcessor->addMessage($notification));
        } catch (PrestaShopDatabaseException $e) {
            $this->fail($e->getMessage());
        } catch (PrestaShopException $e) {
            $this->fail($e->getMessage());
        }
    }
}
