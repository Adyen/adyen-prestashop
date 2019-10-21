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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This function is automatically called on version upgrades.
 *
 * Version 1.1.0 introduces refund feature
 *
 * @param Adyen $module
 * @return bool
 */
function upgrade_module_1_1_0(Adyen $module)
{
   $module->registerHook('actionOrderSlipAdd');
   $module->registerHook('displayPaymentTop');
   return true;
}
