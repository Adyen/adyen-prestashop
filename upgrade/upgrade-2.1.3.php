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
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function upgrade_module_2_1_3(AdyenOfficial $module)
{
    return install_new_tab($module) && uninstall_old_tab();
}

/**
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function install_new_tab(AdyenOfficial $module)
{
    if ((int)Tab::getIdFromClassName('AdminAdyenOfficialPrestashopCron')) {
        return true;
    }

    return $module->installTab();
}

/**
 * @return bool
 */
function uninstall_old_tab()
{
    try {
        $id_tab = (int)Tab::getIdFromClassName('AdminAdyenPrestashopCron');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
    } catch (PrestaShopDatabaseException $e) {
        return false;
    } catch (PrestaShopException $e) {
        return false;
    }
    return false;
}
