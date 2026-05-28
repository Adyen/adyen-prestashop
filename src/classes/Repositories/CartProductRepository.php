<?php

namespace AdyenPayment\Classes\Repositories;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class CartProductRepository
 */
class CartProductRepository
{
    /**
     * Updates delivery address.
     *
     * @param int $cartId
     * @param int $addressId
     *
     * @return bool
     */
    public function updateDeliveryAddress(int $cartId, int $addressId): bool
    {
        return \Db::getInstance()->update(
            'cart_product',
            ['id_address_delivery' => $addressId],
            "id_cart=$cartId"
        );
    }
}
