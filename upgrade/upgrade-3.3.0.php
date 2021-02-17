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
function upgrade_module_3_3_0(AdyenOfficial $module)
{
    return $module->createPaymentNeedsAttentionOrderStatus() && alterAdyenPaymentResponseTable();
}

/**
 * @return bool
 */
function alterAdyenPaymentResponseTable()
{
    $db = Db::getInstance();
    $query = 'ALTER TABLE `' . _DB_PREFIX_ . 'adyen_payment_response`
            ADD COLUMN `request_amount` int NOT NULL COMMENT \'Payment amount in the request\' AFTER `id_cart`,
            ADD COLUMN `request_currency` varchar(3) NOT NULL COMMENT \'Payment currency in the request\'
                AFTER `request_amount`';

    return $db->execute($query);
}
