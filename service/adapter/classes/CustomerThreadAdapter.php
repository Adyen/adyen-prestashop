<?php

namespace Adyen\PrestaShop\service\adapter\classes;

class CustomerThreadAdapter
{
    /**
     * Returns Customer thread instance by customer email address and order id
     *
     * @param $email
     * @param $orderId
     *
     * @return \CustomerThread
     */
    public function getCustomerThreadByEmailAndOrderId($email, $orderId)
    {
        return new \CustomerThread(\CustomerThread::getIdCustomerThreadByEmailAndIdOrder($email, $orderId));
    }
}
