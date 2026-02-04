<?php

namespace AdyenPayment\Classes\Repositories;

class OrderRepository
{
    public function getOrdersByIds(array $orderIds)
    {
        $query = new \DbQuery();
        $query->select('*')
            ->from('orders')
            ->where('id_cart in (' . implode(',', array_map('pSQL', $orderIds)) . ')');

        return \Db::getInstance()->executeS($query);
    }
}
