<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades module to version 7.5.1.
 *
 * Registers the displayHeader hook that renders the Adyen Web SDK tags with Subresource Integrity.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function upgrade_module_7_5_1(AdyenOfficial $module): bool
{
    return (bool) $module->registerHook('displayHeader');
}
