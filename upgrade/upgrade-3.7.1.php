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
function upgrade_module_3_7_1(AdyenOfficial $module)
{
    return set_waiting_for_payment_status_logable_to_false();
}

/**
 * Set the logable field of the WAITING_FOR_PAYMENT status to false
 *
 * @return bool
 */
function set_waiting_for_payment_status_logable_to_false()
{
    $orderStateConfigurationId = Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT');

    $orderState = false;
    if ($orderStateConfigurationId) {
        /** @var $orderState OrderState */
        $orderState = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
                SELECT *
                FROM `' . _DB_PREFIX_ . 'order_state`
                WHERE deleted = 0 AND `id_order_state` = ' . (int)$orderStateConfigurationId
        );
    }

    if (empty($orderState)) {
        return false;
    }

    return Db::getInstance(_PS_USE_SQL_SLAVE_)->update(
        'order_state',
        array('logable' => false),
        'id_order_state = ' . (int)$orderStateConfigurationId,
        1
    );
}
