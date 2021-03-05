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
        // To save the secure key of current cart id and reassign the same to new cart
        $old_cart_secure_key = $cart->secure_key;
        // To save the customer id of current cart id and reassign the same to new cart
        $old_cart_customer_id = (int)$cart->id_customer;

        // To fetch the current cart products
        $cart_products = $cart->getProducts();
        // Creating new cart object
        $context->cart = new \Cart();
        $context->cart->id_lang = $context->language->id;

        $context->cart->id_currency = $context->currency->id;
        $context->cart->secure_key = $old_cart_secure_key;
        // to add new cart
        $context->cart->add();
        $newCartId  = $context->cart->id;
        // to update the new cart
        foreach ($cart_products as $product) {
            $context->cart->updateQty(
                (int)$product['quantity'],
                (int)$product['id_product'],
                (int)$product['id_product_attribute']
            );
        }
        if ($context->cookie->id_guest) {
            $guest = new \Guest($context->cookie->id_guest);
            $context->cart->mobile_theme = $guest->mobile_theme;
        }

        // Field does not exist in prestashop 16
        if (!$isPrestashop16) {
            // Get the checkout_session_data field of the previous cart
            $checkoutSessionData = \Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int)$cart->id
            );

            // Update the checkout_session_data field of the new cart
            \Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'cart SET checkout_session_data = "' . pSQL($checkoutSessionData) . '"
        WHERE id_cart = ' . (int)$newCartId
            );
        }

        // to map the new cart with the customer
        $context->cart->id_customer = $old_cart_customer_id;
        $context->cart->id_guest = $cart->id_guest;
        // to save the new cart
        $context->cart->save();
        if ($context->cart->id) {
            $context->cookie->id_cart = (int)$context->cart->id;
            $context->cookie->write();
        }
    }
}
