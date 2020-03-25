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

namespace Adyen\PrestaShop\service;

class Gender
{
    const MALE_ID = 1;
    const FEMALE_ID = 2;
    const MALE_VALUE = 'MALE';
    const FEMALE_VALUE = 'FEMALE';
    const UNKNOWN_VALUE = 'UNKNOWN';

    /**
     * @var array
     */
    private static $genderMap = array(
        self::MALE_ID => self::MALE_VALUE,
        self::FEMALE_ID => self::FEMALE_VALUE
    );

    /**
     * Returns 'MALE' or 'FEMALE' by PrestaShop gender id
     *
     * @param $genderId
     * @return mixed|string
     */
    public function getAdyenGenderValueById($genderId)
    {
        if (isset(self::$genderMap[$genderId])) {
            return self::$genderMap[$genderId];
        }

        // If gender is not available in the map, fall back to self::UNKNOWN_VALUE
        return self::UNKNOWN_VALUE;
    }
}
