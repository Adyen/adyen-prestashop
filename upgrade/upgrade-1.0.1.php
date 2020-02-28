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
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This file declares a function and checks if PrestaShop is loaded to follow
// PrestaShop's good practices, which breaks a PSR1 element.
//phpcs:disable PSR1.Files.SideEffects

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This function is automatically called on version upgrades.
 *
 * Version 1.0.1 introduces a database table for notifications from Adyen.
 *
 * @param Adyen $module
 * @return bool
 */
function upgrade_module_1_0_1($module)
{
    $module->createAdyenNotificationTable();
    $module->updateCronJobToken();
    $module->installTab();
    return true;
}
