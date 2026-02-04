<?php

namespace AdyenPayment\Classes\Repositories;

class ProductRepository
{
    /**
     * Retrieves product id by product name.
     *
     * @param string $name
     *
     * @return false|string|null
     */
    public function getProductIdByProductName(string $name)
    {
        return \Db::getInstance()->getValue('
        SELECT id_product
        FROM ' . _DB_PREFIX_ . 'product_lang
        WHERE name LIKE "%' . (string) $name . '%"');
    }
}
