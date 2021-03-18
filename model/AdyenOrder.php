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

namespace Adyen\PrestaShop\model;

use OrderHistory;
use PrestaShopException;

class AdyenOrder extends \OrderCore
{
    /**
     * AdyenOrder constructor
     *
     * @param null $id
     * @param null $id_lang
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function __construct($id = null, $id_lang = null)
    {
        parent::__construct($id, $id_lang);
    }

    /**
     * Set current order status
     *
     * @param int $id_order_state
     * @param int $id_employee
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function setCurrentState($id_order_state, $id_employee = 0)
    {
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
