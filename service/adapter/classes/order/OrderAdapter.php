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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\adapter\classes\order;

use Order;
use OrderSlip;
use PrestaShopDatabaseException;
use PrestaShopException;

class OrderAdapter
{
    /**
     * OrderAdapter constructor.
     *
     * @throws \Adyen\AdyenException
     */
    public function __construct()
    {
        $adyenHelperFactory = new \Adyen\PrestaShop\service\helper\DataFactory();
        $this->helperData = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );
    }

    /**
     * Returns the order instance for cart id
     *
     * @param $cartId
     * @return null|\Order
     */
    public function getOrderByCartId($cartId)
    {
        $order = null;

        if ($this->helperData->isPrestashop16()) {
            $orderId = \Order::getOrderByCartId($cartId);
            if ($orderId) {
                $order = new \Order($orderId);
            }
        } else {
            $order = \Order::getByCartId($cartId);
        }

        return $order;
    }

    /**
     * @param string $orderSlipId
     *
     * @return Order
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrderByOrderSlipId($orderSlipId)
    {
        $orderSlip = new OrderSlip($orderSlipId);
        return new Order($orderSlip->id_order);
    }
}