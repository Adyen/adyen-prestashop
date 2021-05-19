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
 * @copyright (c) 2021 Adyen B.V.
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
function upgrade_module_3_6_0(AdyenOfficial $module)
{
    return $module->copyEmailTemplates() && install_new_tabs($module) && copy_order_history_override($module);
}

/**
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function install_new_tabs(AdyenOfficial $module)
{
    if (version_compare(_PS_VERSION_, '1.7', '<')) {
        $parentTab = (int)Tab::getIdFromClassName('AdminParentModules');
        $namePrefix = 'Adyen ';
        $adyenTabResult = true;
    } else {
        $parentTab = (int)Tab::getIdFromClassName('AdminAdyenOfficialPrestashop');
        if (!$parentTab) {
            // Parent adyen tab
            $adyenTab = new Tab();
            $adyenTab->id_parent = (int)Tab::getIdFromClassName('AdminParentModulesSf');
            $adyenTab->active = 1;
            $adyenTab->name = array();
            foreach (Language::getLanguages() as $lang) {
                $adyenTab->name[$lang['id_lang']] = 'Adyen Module';
            }
            $adyenTab->class_name = 'AdminAdyenOfficialPrestashop';
            $adyenTab->module = $module->name;

            $adyenTabResult = $adyenTab->add();
            $parentTab = (int)Tab::getIdFromClassName('AdminAdyenOfficialPrestashop');
        } else {
            $adyenTabResult = true;
        }
        $namePrefix = '';
    }

    $logFetcherTabId = (int)Tab::getIdFromClassName('AdminAdyenOfficialPrestashopLogFetcher');
    if (!$logFetcherTabId) {
        // Log tab
        $logTab = new Tab();
        $logTab->id_parent = $parentTab;
        $logTab->active = 1;
        $logTab->name = array();
        foreach (Language::getLanguages() as $lang) {
            $logTab->name[$lang['id_lang']] = $namePrefix . 'Logs';
        }
        $logTab->class_name = 'AdminAdyenOfficialPrestashopLogFetcher';
        $logTab->module = $module->name;
        $logTabResult = $logTab->add();
    } else {
        $logTabResult = true;
    }

    $validatorTabId = (int)Tab::getIdFromClassName('AdminAdyenOfficialPrestashopValidator');
    if (!$validatorTabId) {
        // Validator tab
        $validatorTab = new Tab();
        $validatorTab->id_parent = $parentTab;
        $validatorTab->active = 1;
        $validatorTab->name = array();
        foreach (Language::getLanguages() as $lang) {
            $validatorTab->name[$lang['id_lang']] = $namePrefix . 'Validator';
        }
        $validatorTab->class_name = 'AdminAdyenOfficialPrestashopValidator';
        $validatorTab->module = $module->name;
        $validatorTabResult = $validatorTab->add();
    } else {
        $validatorTabResult = true;
    }

    return $logTabResult && $adyenTabResult && $validatorTabResult;
}

/**
 * @return bool
 */
function copy_order_history_override(AdyenOfficial $module)
{
    $orderHistoryFile = _PS_MODULE_DIR_ . $module->name . '/override/classes/order/OrderHistory.php';
    $destinationDir = _PS_ROOT_DIR_.'/override/classes/order/OrderHistory.php';

    return copy($orderHistoryFile, $destinationDir);
}
