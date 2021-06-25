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
 * This function is automatically called on version upgrade
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 */
function upgrade_module_3_7_0(AdyenOfficial $module)
{
    return $module->setDefaultConfigurationForEnableAdyenCheckoutStyling() &&
        add_payment_display_collapse($module);
}

/**
 * On 1.6, check if payment display collapse has been added trough a previous installation,
 * if not add it
 *
 * @param AdyenOfficial $module
 * @return bool
 */
function add_payment_display_collapse(AdyenOfficial $module)
{
    if (version_compare(_PS_VERSION_, '1.7', '>=')) {
        return true;
    }

    $payment_display_collapse = Configuration::get(
        'ADYEN_PAYMENT_DISPLAY_COLLAPSE',
        null,
        null,
        null,
        null
    );

    if (is_null($payment_display_collapse)) {
        return $module->setDefaultConfigurationForPaymentDisplayCollapse();
    }

    return true;
}
