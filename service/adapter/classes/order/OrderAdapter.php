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

class OrderAdapter
{
    /**
     * OrderAdapter constructor.
     *
     * @throws \Adyen\AdyenException
     */
    public function __construct()
    {
        $this->helperData = Adapter_ServiceLocator::get('Adyen\PrestaShop\helper\Data');
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
}