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

use Cart;

class Checkout extends \Adyen\Service\Checkout
{
    CONST PAYMENT_METHOD_STEP = 'checkout-payment-step';
    const IS_REACHABLE = 'step_is_reachable';

    public function __construct(Client $client)
    {
        parent::__construct($client);
    }

    /**
     * @param Cart $cart
     */
    public function isPaymentMethodStepNext(Cart $cart)
    {
        $checkoutSessionData = \Db::getInstance()->getValue(
            'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int)$cart->id
        );

        $jsonData = json_decode($checkoutSessionData, true);

        if ($jsonData[self::PAYMENT_METHOD_STEP][self::IS_REACHABLE]) {
            return true;
        }

        return false;
    }
}
