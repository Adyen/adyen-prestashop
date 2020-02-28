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
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\adapter\classes;

use Cache;
use Db;

class State
{
    /**
     * Retrieves Iso code for a state by id
     *
     * @param $id_state
     *
     * @return bool
     */
    public function getIsoById($id_state)
    {
        if (!$id_state) {
            return false;
        }

        $cache_id = 'State::getIsoById_' . (int)$id_state;
        if (!Cache::isStored($cache_id)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
				SELECT `iso_code`
				FROM `' . _DB_PREFIX_ . 'state`
				WHERE `id_state` = ' . (int)$id_state);

            Cache::store($cache_id, $result);
            return $result;
        }

        return Cache::retrieve($cache_id);
    }
}
