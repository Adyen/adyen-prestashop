<?php

use AdyenPayment\Classes\Bootstrap;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'Autoloader.php';

/**
 * Upgrades module to version 5.0.0.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 * @throws Exception
 */
function upgrade_module_5_1_9(AdyenOfficial $module): bool
{
    Autoloader::setFileExt('.php');
    spl_autoload_register('Autoloader::loader');
    Shop::setContext(ShopCore::CONTEXT_ALL);
    $installer = new \AdyenPayment\Classes\Utility\Installer($module);

    Bootstrap::init();

    return $installer->deactivateOldCustomOrderStates();
}
