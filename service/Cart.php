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

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use CartRuleCore as CartRule;

class Cart
{
    /**
     * @var Adyen\PrestaShop\service\Logger
     */
    private $logger;
 
    public function __construct()
    {
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
    }

    /**
     * Clones cart and updates the context
     *
     * @param \Context $context
     * @param \Cart $cart
     * @param bool $isPrestashop16
     */
    public function cloneCurrentCart(\Context $context, \Cart $cart, bool $isPrestashop16)
    {
        $duplication = $cart->duplicate();

        if (!$duplication
            || !is_object($duplication['cart'])
            || !$duplication['cart']->id
            || !$duplication['success']) {
            $this->logger->error('Adyen module was unable to duplicate cart with id: ' . $cart->id);
        } else {
            $context->cookie->id_cart = $duplication['cart']->id;
            $context->cart = $duplication['cart'];
            CartRule::autoAddToCart($context);

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
            } else {
                $context->smarty->assign('cart_qties', $context->cart->nbProducts());
            }

            $context->cookie->write();
        }
    }
}
