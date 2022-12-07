<?php

namespace Adyen\PrestaShop\service\adapter\classes;

class Country
{
    /**
     * @param $countryId
     *
     * @return string
     */
    public function getIsoById($countryId)
    {
        return \CountryCore::getIsoById($countryId);
    }
}
