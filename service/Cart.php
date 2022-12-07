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

class Cart
{
    /**
     * Clones cart and updates the context
     *
     * @param \Context $context
     * @param \Cart $cart
     * @param bool $isPrestashop16
     */
    public function cloneCurrentCart(\Context $context, \Cart $cart, $isPrestashop16)
    {
        $duplication = $cart->duplicate();
        if (!$duplication || !$duplication['success'] || !\Validate::isLoadedObject($duplication['cart'])) {
            return;
        }

        $context->cookie->id_cart = $duplication['cart']->id;
        $context->cart = $duplication['cart'];
        $context->smarty->assign('cart_qties', $context->cart->nbProducts());
        \CartRule::autoAddToCart($context);

        // Field does not exist in prestashop 16
        // Else set the cart qties manually to ensure consistency with layout header
        if (!$isPrestashop16) {
            // Get the checkout_session_data field of the previous cart
            $checkoutSessionData = \Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int)$cart->id
            );

            // Update the checkout_session_data field of the new cart
            \Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'cart SET checkout_session_data = "' . pSQL($checkoutSessionData) . '"
                WHERE id_cart = ' . (int)$context->cart->id
            );
        }

        $context->cookie->write();
    }
}
