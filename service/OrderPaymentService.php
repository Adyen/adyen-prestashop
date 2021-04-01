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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service;

use OrderCore;
use OrderPayment;

class OrderPaymentService
{
    const PAYMENT_METHOD_ADYEN = 'Adyen';

    /**
     * Set the transaction_id field of the orderPayment IFF the current value is empty
     *
     * @param OrderPayment $orderPayment
     * @param string $pspReference
     *
     * @return OrderPayment
     * @throws \PrestaShopException
     */
    public function addPspReferenceForOrderPayment(OrderPayment $orderPayment, $pspReference)
    {
        if (\Validate::isLoadedObject($orderPayment)) {
            if (empty($orderPayment->transaction_id)) {
                $orderPayment->transaction_id = $pspReference;
                $orderPayment->save();
            }
        }

        return $orderPayment;
    }

    /**
     * Get the first order_payment linked to the order
     *
     * @param OrderCore $order
     * @return false|OrderPayment
     * @throws \PrestaShopException
     */
    public function getAdyenOrderPayment(OrderCore $order)
    {
        if (\Validate::isLoadedObject($order)) {
            $paymentCollection = $order->getOrderPaymentCollection()
                ->where('payment_method', '=', self::PAYMENT_METHOD_ADYEN);

            return $paymentCollection->getFirst();
        }

        return null;
    }
}
