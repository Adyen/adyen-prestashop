<?php

namespace AdyenPayment\Classes\Repositories;

use Db;
use mysqli_result;
use PDOStatement;

/**
 * Class CountryRepository
 *
 * @package AdyenPayment\Classes\Repositories
 */
class CountryRepository
{
    /**
     * Returns countries that are not restricted for the module.
     *
     * @param int $moduleId
     * @param int $shopId
     *
     * @return array|bool|mysqli_result|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
     */
    public function getModuleCountries(int $moduleId, int $shopId)
    {
        $sql = 'SELECT c.*
				FROM `' . _DB_PREFIX_ . 'module_country` mc
				LEFT JOIN `' . _DB_PREFIX_ . 'country` c ON c.`id_country` = mc.`id_country`
				WHERE mc.`id_module` = ' . (int)$moduleId . '
					AND c.`active` = 1
					AND mc.id_shop = ' . (int)$shopId . '
				ORDER BY c.`iso_code` ASC';

        return Db::getInstance()->executeS($sql);
    }
}