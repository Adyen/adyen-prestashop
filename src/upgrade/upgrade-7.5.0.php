<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades module to version 7.5.0.
 *
 * Provisions the PrestaShop Account and CloudSync companion modules for existing installations.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function upgrade_module_7_5_0(AdyenOfficial $module): bool
{
    $integrationService = $module->getIntegrationService();

    $integrationService->provisionPsAccounts();
    $integrationService->provisionPsEventBus();

    return true;
}
