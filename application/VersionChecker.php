<?php

namespace Adyen\PrestaShop\application;

class VersionChecker
{
    /**
     * Determine if PrestaShop is 1.6 or not
     *
     * @return bool
     */
    public function isPrestaShop16()
    {
        if (version_compare(_PS_VERSION_, '1.6', '>=')
            && version_compare(_PS_VERSION_, '1.7', '<')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Verifies if the current PrestaShop version is supported or not by the plugin
     *
     * @return bool
     */
    public function isPrestaShopSupportedVersion()
    {
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            return false;
        }

        return true;
    }
}
