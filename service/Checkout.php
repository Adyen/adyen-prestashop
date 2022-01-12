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
use Cart;

class Checkout extends \Adyen\Service\Checkout
{
    const PAYMENT_METHOD_STEP = 'checkout-payment-step';
    const DELIVERY_STEP = 'checkout-delivery-step';
    const IS_REACHABLE = 'step_is_reachable';
    const IS_COMPLETE = 'step_is_complete';

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
    }

    /**
     * Check if the payment method step is next in the checkout process and if so,
     * return true to require fetching payment methods. If an error/exception occurs
     * return true to require fetching payment methods, to be on the safe side.
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function requireFetchPaymentMethods(Cart $cart)
    {
        $isVirtualCart = $cart->isVirtualCart();
        try {
            $checkoutSessionData = \Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int)$cart->id
            );

            if (empty($checkoutSessionData)) {
                $this->logger->error('Session data is empty: %s', $checkoutSessionData);

                return true;
            }

            $jsonData = json_decode($checkoutSessionData, true);

            if (is_null($jsonData) && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error(sprintf('Invalid session JSON data: %s', $checkoutSessionData));

                return true;
            }

            // Check if all keys exist, and then check if the delivery step is complete and payment method is reachable
            if (($isVirtualCart || array_key_exists(self::DELIVERY_STEP, $jsonData)) &&
                array_key_exists(self::IS_COMPLETE, $jsonData[self::DELIVERY_STEP]) &&
                array_key_exists(self::PAYMENT_METHOD_STEP, $jsonData) &&
                array_key_exists(self::IS_REACHABLE, $jsonData[self::PAYMENT_METHOD_STEP]) &&
                ($isVirtualCart || $jsonData[self::DELIVERY_STEP][self::IS_COMPLETE]) &&
                $jsonData[self::PAYMENT_METHOD_STEP][self::IS_REACHABLE]
            ) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('An error occurred while checking if the payment method step is next: %s', $e->getMessage())
            );

            return true;
        }
    }
}
