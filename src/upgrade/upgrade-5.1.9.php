<?php

use Adyen\Core\Infrastructure\Logger\Logger;
use AdyenPayment\Classes\Bootstrap;

require_once 'Autoloader.php';

/**
 * Upgrades module to version 5.1.9.
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
    $installer = new AdyenPayment\Classes\Utility\Installer($module);

    Bootstrap::init();
    try {
        $installer->deactivateOldCustomOrderStates();
    } catch (Throwable $exception) {
        Logger::logError(
            'Adyen plugin migration to 5.1.9 failed. Reason: ' .
            $exception->getMessage() . ' .Trace: ' . $exception->getTraceAsString()
        );

        return false;
    }

    return true;
}
