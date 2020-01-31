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

use Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\notification\NotificationProcessor;
use Db;
use Mockery as m;

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
     * @var Adyen\PrestaShop\helper\Data|\PHPUnit_Framework_MockObject_MockObject $adyenHelper
     */
    private $adyenHelper;

    /**
     * @var Db|\PHPUnit_Framework_MockObject_MockObject $dbInstance
     */
    private $dbInstance;

    /**
     * @var CustomerThreadAdapter|\PHPUnit_Framework_MockObject_MockObject $customerThreadAdapter
     */
    private $customerThreadAdapter;

    /**
     *
     */
    protected function setUp()
    {
        self::$functions = m::mock();

        $this->logger = $this->getMockBuilder(\Adyen\PrestaShop\service\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        //$this->logger->method('error');

        $this->adyenHelper = $this->getMockBuilder(\Adyen\PrestaShop\helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dbInstance = $this->getMockBuilder(Db::class)->disableOriginalConstructor()->getMock();

        // Mock customerThread
        $customerThreadMock = $this->getMockBuilder(\CustomerThread::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customerThreadMock->id = 1;

        // Mock customerThreadAdapter
        $this->customerThreadAdapter = $this->getMockBuilder(CustomerThreadAdapter::class)->disableOriginalConstructor()->getMock();
        $this->customerThreadAdapter->method('getCustomerThreadByEmailAndOrderId')->willReturn($customerThreadMock);
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
        $customerMock = $this->getMockBuilder(\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customerMock->email = 'test@test.com';

        // Mock order
        $orderMock = $this->getMockBuilder(\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderMock->method('getCustomer')->willReturn($customerMock);
        $orderMock->id_customer = 1;

        // Mock order adapter
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)->disableOriginalConstructor()->getMock();
        $orderAdapter->method('getOrderByCartId')->willReturn($orderMock);

        // Mock customerMessage add
        $customerMessageMock = m::mock('overload:CustomerMessage');
        $customerMessageMock->shouldReceive('add')
            ->once()
            ->andReturn(true);

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->dbInstance,
            $orderAdapter,
            $this->customerThreadAdapter,
            $this->logger
        );

        $this->assertTrue($notificationProcessor->addMessage($notification));
    }

    public function testIsMessageAddedOrderDoesNotExist()
    {
        $notification = json_decode(file_get_contents(__DIR__ . '/unprocessed-notification.json'), true);


        // Mock order adapter
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)->disableOriginalConstructor()->getMock();
        $orderAdapter->method('getOrderByCartId')->willReturn(null);

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->dbInstance,
            $orderAdapter,
            $this->customerThreadAdapter,
            $this->logger
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
        $orderMock = $this->getMockBuilder(\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderMock->method('getCustomer')->willReturn(null);
        $orderMock->id_customer = null;

        // Mock order adapter
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)->disableOriginalConstructor()->getMock();
        $orderAdapter->method('getOrderByCartId')->willReturn($orderMock);

        $notificationProcessor = new NotificationProcessor(
            $this->adyenHelper,
            $this->dbInstance,
            $orderAdapter,
            $this->customerThreadAdapter,
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Customer with id: "" cannot be found for order with id: "" while notification with id: "1" was processed.');

        $this->assertFalse($notificationProcessor->addMessage($notification));
    }
}
