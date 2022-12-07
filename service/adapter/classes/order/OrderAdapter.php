<?php

namespace Adyen\PrestaShop\service\adapter\classes\order;

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Order;

class OrderAdapter
{
    /**
     * OrderAdapter constructor.
     *
     * @throws \Adyen\AdyenException
     */
    public function __construct()
    {
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
    }

    /**
     * Returns the order instance for cart id
     *
     * @param $cartId
     *
     * @return \Order|null
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
     * @return \Order
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getOrderByOrderSlipId($orderSlipId)
    {
        $orderSlip = new \OrderSlip($orderSlipId);

        return new \Order($orderSlip->id_order);
    }
}
