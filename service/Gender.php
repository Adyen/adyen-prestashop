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
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service;

class Gender
{
    const MALE_ID = 1;
    const FEMALE_ID = 2;
    const MALE_VALUE = 'M';
    const FEMALE_VALUE = 'F';

    /**
     * @var array
     */
    private static $genderMap = array(
        self::MALE_ID => self::MALE_VALUE,
        self::FEMALE_ID => self::FEMALE_VALUE
    );

    /**
     * Returns 'M' or 'F' by PrestaShop gender id
     *
     * @param int $genderId
     * @return mixed
     * @throws \Exception
     */
    public function getAdyenGenderValueById($genderId)
    {
        if (isset(self::$genderMap[$genderId])) {
            return self::$genderMap[$genderId];
        }

        throw new \Exception('Adyen gender value not found by id: ' . (int)$genderId);
    }
}
