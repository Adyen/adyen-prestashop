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
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\adapter\classes;

use PrestaShop\PrestaShop\Adapter\CoreException;

class ServiceLocator
{
    /**
     * @param string $serviceName
     *
     * @return mixed|object
     * @throws CoreException
     */
    public static function get($serviceName)
    {
        if (class_exists('Adapter_ServiceLocator')) {
            return \Adapter_ServiceLocator::get($serviceName);
        } else {
            return \PrestaShop\PrestaShop\Adapter\ServiceLocator::get($serviceName);
        }
    }
}
