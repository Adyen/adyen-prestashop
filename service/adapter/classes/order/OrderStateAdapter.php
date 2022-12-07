<?php

namespace Adyen\PrestaShop\service\adapter\classes\order;

class OrderStateAdapter
{
    /**
     * @param int $orderStateId
     *
     * @return mixed
     */
    public function getOrderStateById($orderStateId)
    {
        $cache_id = 'OrderStateAdapter::getOrderStateById' . (int) $orderStateId;
        if (!\Cache::isStored($cache_id)) {
            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                '
                    SELECT *
                    FROM `' . _DB_PREFIX_ . 'order_state`
                    WHERE deleted = 0 AND `id_order_state` = ' . (int) $orderStateId
            );

            \Cache::store($cache_id, $result);

            return $result;
        }

        return \Cache::retrieve($cache_id);
    }
}
