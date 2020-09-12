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

namespace Adyen\PrestaShop\service\builder;

class Payment
{
    /**
     * Add additional fields in the payments request which cannot be retrieved form the frontend
     *
     * @param $currencyIso
     * @param $formattedValue
     * @param $reference
     * @param $merchantAccount
     * @param $returnUrl
     * @param $origin
     * @param array $request
     * @return array|mixed
     */
    public function buildPaymentData(
        $currencyIso,
        $formattedValue,
        $reference,
        $merchantAccount,
        $returnUrl,
        $origin,
        $request = array()
    ) {
        $request['amount'] = array(
            'currency' => $currencyIso,
            'value' => $formattedValue
        );

        $request["reference"] = $reference;
        $request['merchantAccount'] = $merchantAccount;
        $request['returnUrl'] = $returnUrl;
        $request['additionalData']['allow3DS2'] = true;
        $request['channel'] = 'web';
        $request['origin'] = $origin;
        $request['shopperInteraction'] = 'Ecommerce';

        return $request;
    }
}
