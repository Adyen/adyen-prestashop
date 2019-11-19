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
 *
 *
 * @param Adyen $module
 *
 * @return bool
 */
function upgrade_module_1_2_0(Adyen $module)
{
    return $module->registerHook('actionFrontControllerSetMedia')
        && $module->unregisterHook('header')
        && $module->unregisterHook('displayHeader');
}
