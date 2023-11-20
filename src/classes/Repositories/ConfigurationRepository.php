<?php

namespace AdyenPayment\Classes\Repositories;

use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use PrestaShop\PrestaShop\Adapter\Entity\Shop;

/**
 * Class ConfigurationRepository
 *
 * @package AdyenPayment\Classes\Repositories
 */
class ConfigurationRepository
{
    public const TABLE_NAME = 'configuration';

    /**
     * @throws \PrestaShopDatabaseException
     */
    public function isStoreInMaintenanceMode(int $storeId): bool
    {
        $maintenanceMode = false;
        $query = 'SELECT *
                    FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '`
                    WHERE `name` = "PS_SHOP_ENABLE"';

        $manuallyManagedStores = \Db::getInstance()->executeS($query);

        if (count(Shop::getShops()) === 1) {
            $filteredStores = array_filter($manuallyManagedStores, static function ($store) {
                return $store['id_shop'] === null;
            });

            $defaultConfig = reset($filteredStores);

            return !$defaultConfig['value'];
        }

        foreach ($manuallyManagedStores as $manualStore) {
            if ($manualStore['id_shop'] === (string)$storeId && $manualStore['value'] === null) {
                $maintenanceMode = true;
            }
        }

        return $maintenanceMode;
    }
}
