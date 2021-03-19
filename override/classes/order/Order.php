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

class Order extends OrderCore
{
    /**
     * Set current order status
     *
     * If prestashop version is greater or equal to 1.7.7 call parent function
     * Else, call this adyen function (uses $use_existings_payment parameter)
     *
     * @param int $id_order_state
     * @param int $id_employee
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function setCurrentState($id_order_state, $id_employee = 0)
    {
        if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
            return parent::setCurrentState($id_order_state, $id_employee);
        }

        if (empty($id_order_state)) {
            return false;
        }

        $history = new OrderHistory();
        $history->id_order = (int) $this->id;
        $history->id_employee = (int) $id_employee;
        $use_existings_payment = !$this->hasInvoice();
        $history->changeIdOrderState((int) $id_order_state, $this, $use_existings_payment);
        $res = Db::getInstance()->getRow('
            SELECT `invoice_number`, `invoice_date`, `delivery_number`, `delivery_date`
            FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_order` = ' . (int) $this->id);
        $this->invoice_date = $res['invoice_date'];
        $this->invoice_number = $res['invoice_number'];
        $this->delivery_date = $res['delivery_date'];
        $this->delivery_number = $res['delivery_number'];
        $this->update();

        $history->addWithemail();

        return true;
    }

}
