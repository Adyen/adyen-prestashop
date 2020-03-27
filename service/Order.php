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

class Order
{
    public function addPaymentDataToOrderFromResponse($order, $response)
    {
        if (\Validate::isLoadedObject($order)) {
            // Save available data into the order_payment table
            $paymentCollection = $order->getOrderPaymentCollection();
            foreach ($paymentCollection as $payment) {
                $cardSummary = !empty($response['additionalData']['cardSummary'])
                    ? pSQL($response['additionalData']['cardSummary'])
                    : '****';
                $cardBin = !empty($response['additionalData']['cardBin'])
                    ? pSQL($response['additionalData']['cardBin'])
                    : '******';
                $paymentMethod = !empty($response['additionalData']['paymentMethod'])
                    ? pSQL($response['additionalData']['paymentMethod'])
                    : 'Adyen';
                $expiryDate = !empty($response['additionalData']['expiryDate'])
                    ? pSQL($response['additionalData']['expiryDate'])
                    : '';
                $cardHolderName = !empty($response['additionalData']['cardHolderName'])
                    ? pSQL($response['additionalData']['cardHolderName']) : '';
                $payment->card_number = $cardBin . ' *** ' . $cardSummary;
                $payment->card_brand = $paymentMethod;
                $payment->card_expiration = $expiryDate;
                $payment->card_holder = $cardHolderName;
                $payment->save();
            }
        }
    }
}
