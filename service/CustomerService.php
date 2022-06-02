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
 * @copyright (c) 2022 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service;

use OrderCore;

class CustomerService
{
    /**
     * @param \Customer $customer
     * @param OrderCore $order
     * @param \Shop $shop
     * @param \Language $language
     * @return \CustomerThread
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function createCustomerThread(\Customer $customer, OrderCore $order, \Shop $shop, \Language $language): \CustomerThread
    {
        $customerThread = new \CustomerThread();
        $customerThread->id_contact = 0;
        $customerThread->id_customer = $customer->id;
        $customerThread->id_shop = $shop->id;
        $customerThread->id_order = $order->id;
        $customerThread->id_lang = $language->id;
        $customerThread->email = $customer->email;
        $customerThread->status = 'open';
        $customerThread->token = \Tools::passwdGen(12);
        $customerThread->add();

        return $customerThread;
    }

    /**
     * @param \CustomerThread $customerThread
     * @param string $comment
     * @return \CustomerMessage
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function createCustomerMessage(\CustomerThread $customerThread, string $comment): \CustomerMessage
    {
        // Create message
        $customerMessage = new \CustomerMessage();
        $customerMessage->id_customer_thread = $customerThread->id;
        // employee cannot be empty - default employee id is 1
        $customerMessage->id_employee = 1;
        $customerMessage->message = $comment;
        $customerMessage->private = 1;

        $customerMessage->add();

        return $customerMessage;
    }
}
