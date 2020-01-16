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

namespace Adyen\PrestaShop\service\builder;

class Address extends Builder
{
    /**
     * @var int
     */
    private static $addressTypeBilling = 1;

    /**
     * @var int
     */
    private static $addressTypeDelivery = 2;

    /**
     * @var string
     */
    private static $defaultStreet = 'N/A';

    /**
     * @var string
     */
    private static $defaultPostalCode = '';

    /**
     * @var string
     */
    private static $defaultCity = 'N/A';

    /**
     * @var string
     */
    private static $defaultHouseNumberOrName = '';

    /**
     * @var string
     */
    private static $defaultCountry = 'ZZ';

    /**
     * @param string $street
     * @param string $houseNumberOrName
     * @param string $postalCode
     * @param string $city
     * @param string $stateOrProvince
     * @param string $country
     * @param array $request
     * @return array
     */
    public function buildBillingAddress(
        $street = '',
        $houseNumberOrName = '',
        $postalCode = '',
        $city = '',
        $stateOrProvince = '',
        $country = '',
        $request = array()
    ) {
        return $this->buildAddress(self::$addressTypeBilling, $street, $houseNumberOrName, $postalCode, $city,
            $stateOrProvince, $country, $request);
    }

    /**
     * @param string $street
     * @param string $houseNumberOrName
     * @param string $postalCode
     * @param string $city
     * @param string $stateOrProvince
     * @param string $country
     * @param array $request
     * @return array
     */
    public function buildDeliveryAddress(
        $street = '',
        $houseNumberOrName = '',
        $postalCode = '',
        $city = '',
        $stateOrProvince = '',
        $country = '',
        $request = array()
    ) {
        return $this->buildAddress(self::$addressTypeDelivery, $street, $houseNumberOrName, $postalCode, $city,
            $stateOrProvince, $country, $request);
    }

    /**
     * @param int self::$addressTypeBilling|self::$addressTypeDelivery
     * @param string $street
     * @param string $houseNumberOrName
     * @param string $postalCode
     * @param string $city
     * @param string $stateOrProvince
     * @param string $country
     * @param array $request
     * @return array
     */
    private function buildAddress(
        $addressType,
        $street,
        $houseNumberOrName,
        $postalCode,
        $city,
        $stateOrProvince,
        $country,
        $request
    ) {
        if ('' !== $street) {
            $address["street"] = $street;
        } else {
            $address["street"] = self::$defaultStreet;
        }

        if ('' !== $houseNumberOrName) {
            $address["houseNumberOrName"] = $houseNumberOrName;
        } else {
            $address["houseNumberOrName"] = self::$defaultHouseNumberOrName;
        }

        if ('' !== $postalCode) {
            $address["postalCode"] = $postalCode;
        } else {
            $address["postalCode"] = self::$defaultPostalCode;
        }

        if ('' !== $city) {
            $address["city"] = $city;
        } else {
            $address["city"] = self::$defaultCity;
        }

        if ('' !== $stateOrProvince) {
            $address["stateOrProvince"] = $stateOrProvince;
        }

        if ('' !== $country) {
            $address["country"] = $country;
        } else {
            $address["country"] = self::$defaultCountry;
        }

        // Assigns the address to billing or delivery address depends on the $addressType parameter
        if (self::$addressTypeDelivery == $addressType) {
            $request['deliveryAddress'] = $address;
        } elseif (self::$addressTypeBilling == $addressType) {
            $request['billingAddress'] = $address;
        }

        return $request;
    }
}
