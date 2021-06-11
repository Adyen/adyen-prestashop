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
     * Check if the payment method step is next in the checkout process
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function isPaymentMethodStepNext(Cart $cart)
    {
        try {
            $checkoutSessionData = \Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int)$cart->id
            );

            $jsonData = json_decode($checkoutSessionData, true);

            // If delivery step is complete and payment method is reachable
            if ($jsonData[self::DELIVERY_STEP][self::IS_COMPLETE] &&
                $jsonData[self::PAYMENT_METHOD_STEP][self::IS_REACHABLE]) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('An error while checking if the payment method step is next: %s', $e->getMessage())
            );

            return true;
        }
    }
}
