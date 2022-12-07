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

namespace Adyen\PrestaShop\service\modification;

use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\OrderPaymentService;
use Adyen\Service\Modification;
use Order;
use PHPUnit\Framework\TestCase;

class RefundTest extends TestCase
{
    public function refundDataProvider()
    {
        return [
            ['1234567890abcdef', '123', 'EUR', '1', 'PrestaShopTest', '1'],
            ['abcdef1234567890', '100', 'BRL', '2', 'PrestaShopTest', '1'],
        ];
    }

    /**
     * @dataProvider refundDataProvider
     *
     * @param $pspReference
     * @param $amount
     * @param $currency
     * @param $orderId
     * @param $merchantAccount
     * @param $merchantReference
     *
     * @throws \Adyen\AdyenException
     * @throws \PrestaShopDatabaseException
     */
    public function testRefundProcessCreatesAValidRequest(
        $pspReference,
        $amount,
        $currency,
        $orderId,
        $merchantAccount,
        $merchantReference
    ) {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Modification $modificationClient */
        $modificationClient = $this->getMockBuilder('Adyen\Service\Modification')
            ->disableOriginalConstructor()
            ->getMock();
        $modificationClient->expects($this->once())
            ->method('refund')
            ->with(
                $this->equalTo(
                    [
                        'originalReference' => $pspReference,
                        'modificationAmount' => [
                            'value' => $amount * 100,
                            'currency' => $currency,
                        ],
                        'reference' => (string) $merchantReference,
                        'merchantAccount' => $merchantAccount,
                    ]
                )
            )
            ->willReturn(true);

        // Mock order
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Order $orderMock */
        $orderMock = $this->getMockBuilder(\OrderCore::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderMock->total_paid = $amount;

        /** @var \PHPUnit_Framework_MockObject_MockObject|\OrderSlip $orderSlip */
        $orderSlip = $this->getMockBuilder('OrderSlip')
            ->disableOriginalConstructor()
            ->getMock();
        $orderSlip->id_order = $orderId;
        $orderSlip->amount = $amount;
        $orderSlip->shipping_cost = '0';
        $orderSlip->id = 1;

        $orderPayment = $this->getMockBuilder('OrderPayment')
            ->disableOriginalConstructor()
            ->getMock();

        $orderPayment->transaction_id = $pspReference;

        /** @var \PHPUnit_Framework_MockObject_MockObject|OrderPaymentService $orderPaymentService */
        $orderPaymentService = $this->getMockBuilder(OrderPaymentService::class)
            ->getMock();

        $orderPaymentService->expects($this->once())
            ->method('getAdyenOrderPayment')
            ->with($orderMock)
            ->willReturn($orderPayment);

        /** @var \PHPUnit_Framework_MockObject_MockObject|OrderAdapter $orderAdapter */
        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter->expects($this->once())
            ->method('getOrderByOrderSlipId')
            ->with($orderSlip->id)
            ->willReturn($orderMock);

        $logger = $this->getMockBuilder(\Adyen\PrestaShop\service\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $refund = new Refund(
            $modificationClient,
            $merchantAccount,
            $orderAdapter,
            $orderPaymentService,
            $logger
        );

        $this->assertEquals(true, $refund->request($orderSlip, $currency));
    }
}
