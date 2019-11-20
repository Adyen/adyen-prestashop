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

namespace Adyen\PrestaShop\service\modification;

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

        $pattern = 'select * from ps_adyen_notification a inner join ps_orders o on a.merchant_reference = o.id_cart';
        $pattern = str_replace('*', '.+', $pattern);
        $pattern = str_replace(' ', '\\s+', $pattern);
        /** @var PHPUnit_Framework_MockObject_MockObject|\Db $databaseConnection */
        $databaseConnection = $this->getMockBuilder('Db')
            ->disableOriginalConstructor()
            ->getMock();
        $databaseConnection->expects($this->once())
                           ->method('executeS')
                           ->with($this->matchesRegularExpression("/$pattern/si"))
                           ->willReturn(array(array('pspReference' => $pspReference)));

        $refund = new Refund($modificationClient, $databaseConnection, $merchantAccount);
        $this->assertEquals(true, $refund->request($orderSlip, $currency));
    }
}
