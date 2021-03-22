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

class OrderPaymentService
{
    /**
     * @param OrderCore $order
     * @param string $pspReference
     *
     * @return OrderCore
     * @throws \PrestaShopException
     */
    public function addPspReferenceForOrderPayment(OrderCore $order, $pspReference)
    {
        if (\Validate::isLoadedObject($order)) {
            $paymentCollection = $order->getOrderPaymentCollection()->orderBy('date_add', 'desc');

            // Get the latest transaction
            $payment = $paymentCollection->getFirst();
            if ($payment !== false && empty($payment->transaction_id)) {
                $payment->transaction_id = $pspReference;
                $payment->save();
            }
        }

        return $order;
    }
}
