<?php

namespace Adyen\PrestaShop\service\adapter\classes;

use PrestaShop\PrestaShop\Adapter\CoreException;

class ServiceLocator
{
    /**
     * @param $serviceName
     *
     * @return mixed|object
     *
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
