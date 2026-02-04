<?php

/**
 * @throws PrestaShopDatabaseException
 */
function upgrade_module_7_1_4(AdyenOfficial $module): bool
{
    $query = new DbQuery();
    $query->select('id_cart')
        ->from(bqSQL('cart'), 'c')
        ->leftJoin(bqSQL('address'), 'a1', 'c.id_address_delivery = a1.id_address')
    ->leftJoin(bqSQL('address'), 'a2', 'c.id_address_invoice = a2.id_address')
    ->where('a1.id_address IS NULL OR a2.id_address IS NULL');

    $result = Db::getInstance()->executeS($query);

    foreach ($result as $row) {
        Db::getInstance()->update(bqSQL('cart'), ['id_address_delivery' => 0, 'id_address_invoice' => 0], 'id_cart = ' . $row['id_cart']);
    }

    return true;
}
