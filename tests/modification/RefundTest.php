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

use Adyen\PrestaShop\infra\NotificationRetriever;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\Service\Modification;
use OrderSlip;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

class RefundTest extends TestCase
{
    public function refundDataProvider()
    {
        return array(
            array('1234567890abcdef', '123', 'EUR', '1', 'PrestaShopTest', '1'),
            array('abcdef1234567890', '100', 'BRL', '2', 'PrestaShopTest', '1')
        );
    }

    /**
     * @dataProvider refundDataProvider
     * @param $pspReference
     * @param $amount
     * @param $currency
     * @param $orderId
     * @param $merchantAccount
     * @param $merchantReference
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
        /** @var PHPUnit_Framework_MockObject_MockObject|Modification $modificationClient */
        $modificationClient = $this->getMockBuilder('Adyen\Service\Modification')
                                   ->disableOriginalConstructor()
                                   ->getMock();
        $modificationClient->expects($this->once())
                           ->method('refund')
                           ->with(
                               $this->equalTo(
                                   array(
                                       'originalReference' => $pspReference,
                                       'modificationAmount' => array(
                                           'value' => $amount * 100,
                                           'currency' => $currency
                                       ),
                                       'reference' => (string)$merchantReference,
                                       'merchantAccount' => $merchantAccount
                                   )
                               )
                           )
                           ->willReturn(true);

        /** @var PHPUnit_Framework_MockObject_MockObject|OrderSlip $orderSlip */
        $orderSlip = $this->getMockBuilder('OrderSlip')
                          ->disableOriginalConstructor()
                          ->getMock();
        $orderSlip->id_order = $orderId;
        $orderSlip->amount = $amount;
        $orderSlip->id = 1;

        /** @var PHPUnit_Framework_MockObject_MockObject|NotificationRetriever $notificationRetriever */
        $notificationRetriever = $this->getMockBuilder('Adyen\PrestaShop\infra\NotificationRetriever')
                                      ->disableOriginalConstructor()
                                      ->getMock();
        $notificationRetriever->expects($this->once())
                              ->method('getPSPReferenceByOrderId')
                              ->with($orderId)
                              ->willReturn($pspReference);


        $logger = $this->getMockBuilder(\Adyen\PrestaShop\service\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $refund = new Refund(
            $modificationClient,
            $notificationRetriever,
            $merchantAccount,
            new OrderAdapter(),
            $logger
        );

        $this->assertEquals(true, $refund->request($orderSlip, $currency));
    }
}
