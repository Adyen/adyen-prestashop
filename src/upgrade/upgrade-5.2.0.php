<?php

use Adyen\Core\Infrastructure\Logger\Logger;
use AdyenPayment\Classes\Bootstrap;

require_once 'Autoloader.php';

/**
 * Upgrades module to version 5.2.0.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 * @throws Exception
 */
function upgrade_module_5_2_0(AdyenOfficial $module): bool
{
    Autoloader::setFileExt('.php');
    spl_autoload_register('Autoloader::loader');
    Shop::setContext(ShopCore::CONTEXT_ALL);
    $installer = new AdyenPayment\Classes\Utility\Installer($module);

    Bootstrap::init();
    try {
        $installer->addHook('actionObjectOrderUpdateAfter');
        $installer->addController('AdyenAuthorizationAdjustment');
    } catch (Throwable $exception) {
        Logger::logError(
            'Adyen plugin migration to 5.2.0 failed. Reason: ' .
            $exception->getMessage() . ' .Trace: ' . $exception->getTraceAsString()
        );

        return false;
    }

    return true;
}
