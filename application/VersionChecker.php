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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

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
        if (
            version_compare(_PS_VERSION_, '1.6', '>=')
            && version_compare(_PS_VERSION_, '1.7', '<')
        ) {
            return true;
        }
        return false;
    }
}
