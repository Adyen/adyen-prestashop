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
    /**
     * Set the transaction_id field of the orderPayment IFF the current value is empty
     *
     * @param OrderPayment $orderPayment
     * @param string $pspReference
     *
     * @return OrderCore
     * @throws \PrestaShopException
     */
    public function addPspReferenceForOrderPayment(OrderPayment $orderPayment, $pspReference)
    {
        if (\Validate::isLoadedObject($orderPayment)) {
            if ($orderPayment !== false && empty($orderPayment->transaction_id)) {
                $orderPayment->transaction_id = $pspReference;
                $orderPayment->save();
            }
        }

        return $orderPayment;
    }

    /**
     * @param OrderCore $order
     * @return false|OrderPayment
     * @throws \PrestaShopException
     */
    public function getLatestOrderPayment(OrderCore $order)
    {
        if (\Validate::isLoadedObject($order)) {
            $paymentCollection = $order->getOrderPaymentCollection()->orderBy('date_add', 'desc');

            // Get the latest transaction
            return $paymentCollection->getFirst();
        }

        return false;
    }
}
