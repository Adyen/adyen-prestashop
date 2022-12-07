<?php

namespace Adyen\PrestaShop\service;

class Gender
{
    public const MALE_ID = 1;
    public const FEMALE_ID = 2;
    public const MALE_VALUE = 'MALE';
    public const FEMALE_VALUE = 'FEMALE';
    public const UNKNOWN_VALUE = 'UNKNOWN';

    /**
     * @var array
     */
    private static $genderMap = [
        self::MALE_ID => self::MALE_VALUE,
        self::FEMALE_ID => self::FEMALE_VALUE,
    ];

    /**
     * Returns 'MALE' or 'FEMALE' by PrestaShop gender id
     *
     * @param $genderId
     *
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
