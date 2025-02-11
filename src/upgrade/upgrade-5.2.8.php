<?php

use Adyen\Core\Infrastructure\Logger\Logger;
use AdyenPayment\Classes\Bootstrap;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'Autoloader.php';

/**
 * Upgrades module to version 5.2.8.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function upgrade_module_5_2_8(AdyenOfficial $module): bool
{
    Autoloader::setFileExt('.php');
    spl_autoload_register('Autoloader::loader');
    Shop::setContext(Shop::CONTEXT_ALL);

    Bootstrap::init();

    try {
        // Unregister displayPaymentReturn hook
        if ($module->isRegisteredInHook('displayPaymentReturn')) {
            $module->unregisterHook('displayPaymentReturn');
        }
    } catch (Throwable $exception) {
        Logger::logError(
            'Adyen plugin migration to 5.2.8 failed. Reason: ' .
            $exception->getMessage() . ' .Trace: ' . $exception->getTraceAsString()
        );

        return false;
    }

    return true;
}

