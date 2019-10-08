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
use PHPUnit\Framework\TestCase;

class RefundTest extends TestCase
{
    public function refundDataProvider()
    {
        return [
            ['1234567890abcdef', '123', 'EUR', '1', 'PrestaShopTest', '1-refund'],
            ['abcdef1234567890', '100', 'BRL', '2', 'PrestaShopTest', '2-refund']
        ];
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
        /** @var \PHPUnit_Framework_MockObject_MockObject|Modification $modificationClient */
        $modificationClient = $this->getMockBuilder('Adyen\Service\Modification')
            ->disableOriginalConstructor()
            ->getMock();
        $modificationClient->expects($this->once())
            ->method('refund')
            ->with($this->equalTo([
                'originalReference' => $pspReference,
                'modificationAmount' => [
                    'value' => $amount,
                    'currency' => $currency
                ],
                'reference' => $merchantReference,
                'merchantAccount' => $merchantAccount
            ]))
            ->willReturn(true);

        $pattern = 'select * from ps_adyen_notification a inner join ps_orders o on a.merchant_reference = o.id_order';
        $pattern = str_replace('*', '.+', $pattern);
        $pattern = str_replace(' ', '\\s+', $pattern);
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Db $databaseConnection */
        $databaseConnection = $this->getMockBuilder('Db')
            ->disableOriginalConstructor()
            ->getMock();
        $databaseConnection->expects($this->once())
            ->method('query')
            ->with($this->matchesRegularExpression("/$pattern/si"))
            ->willReturn([['pspReference' => $pspReference, 'orderId' => $orderId]]);

        $refund = new Refund($modificationClient, $databaseConnection, $merchantAccount);
        $this->assertEquals(true, $refund->request($orderId, $amount, $currency));
    }
}
