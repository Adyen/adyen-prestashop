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

namespace Adyen\PrestaShop\service\notification;

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Db;

class NotificationProcessor
{
    /**
     * @var AdyenHelper
     */
    private $helperData;

    /**
     * @var Db
     */
    private $dbInstance;

    /**
     * CronProcessor constructor.
     *
     * @param AdyenHelper $helperData
     * @param Db $dbInstance
     */
    public function __construct(
        AdyenHelper $helperData,
        Db $dbInstance
    ) {
        $this->helperData = $helperData;
        $this->dbInstance = $dbInstance;
    }

    public function doPostProcess()
    {
        $unprocessedNotification = $this->getNextUnprocessedNotification();

        while (!empty($unprocessedNotification)) {
            // update as processing
            $this->updateNotificationAsProcessing($unprocessedNotification['entity_id']);

            // Add cron message to order
            $this->addMessage($unprocessedNotification);

            // processing is done
            $this->updateNotificationAsDone($unprocessedNotification['entity_id']);

            // get next unprocess notification
            $unprocessedNotification = $this->getNextUnprocessedNotification();
        }
    }

    /**
     * @return array|bool|null|object
     */
    protected function getNextUnprocessedNotification()
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'adyen_notification '
            . 'WHERE `done` = "' . 0 . '"'
            . ' AND `processing` = "' . 0 . '"';
        return $this->dbInstance->getRow($sql);
    }

    /**
     * Update the unprocessed and not done notification to processing
     * @param $id
     * @return mixed
     */
    protected function updateNotificationAsProcessing($id)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'adyen_notification '
            . 'SET `processing` = "' . 1 . '"'
            . 'WHERE `done` = "' . 0 . '"'
            . ' AND `processing` = "' . 0 . '"'
            . ' AND `entity_id` = "' . (int)$id . '"';
        return $this->dbInstance->execute($sql);
    }

    /**
     * Update the processed but not done notification to done
     * @param $id
     * @return mixed
     */
    protected function updateNotificationAsDone($id)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'adyen_notification '
            . 'SET `processing` = "' . 0 . '", `done` = "' . 1 . '"'
            . 'WHERE `done` = "' . 0 . '"'
            . ' AND `processing` = "' . 1 . '"'
            . ' AND `entity_id` = "' . (int)$id . '"';
        return $this->dbInstance->execute($sql);
    }

    /**
     * Add order message based on the notification
     *
     * @param $notification
     * @return bool
     */
    protected function addMessage($notification)
    {
        $successResult = (strcmp($notification['success'], 'true') == 0 ||
            strcmp($notification['success'], '1') == 0) ? 'true' : 'false';
        $success = (!empty($notification['reason'])) ? "$successResult <br />reason:" . $notification['reason'] . PHP_EOL : $successResult . PHP_EOL;

        $type = 'Adyen HTTP Notification(s):';
        $comment = sprintf(
            '%s <br /> eventCode: %s <br /> pspReference: %s <br /> paymentMethod: %s <br />' .
            ' success: %s',
            $type,
            $notification['event_code'],
            $notification['pspreference'],
            $notification['payment_method'],
            $success
        );

        if ($this->helperData->isPrestashop16()) {
            $orderId = \Order::getOrderByCartId($notification['merchant_reference']);
            $order = new \Order($orderId);
        } else {
            $order = \Order::getByCartId($notification['merchant_reference']);
        }

        if (empty($order)) {
            $this->helperData->adyenLogger()->logError('Order with id: "' . $notification['merchant_reference'] . '" cannot be found while notification with id: "' . $notification['entity_id'] . '" was processed.');
            return false;
        }

        // Find customer by order id
        $customer = $order->getCustomer();
        if (empty($customer)) {
            $this->helperData->adyenLogger()->logError('Customer with id: "' . $order->id_customer . '" cannot be found for order with id: "' . $order->id . '" while notification with id: "' . $notification['entity_id'] . '" was processed.');
            return false;
        }

        // Find customer thread by order id and customer email
        $customerThread = new \CustomerThread(\CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id));
        if (empty($customerThread->id)) {
            $customer_thread = new \CustomerThread();
            $customer_thread->id_contact = 0;
            $customer_thread->id_customer = (int) $customer->id;
            $customer_thread->id_shop = (int) $this->context->shop->id;
            $customer_thread->id_order = (int) $order->id;
            $customer_thread->id_lang = (int) $this->context->language->id;
            $customer_thread->email = $customer->email;
            $customer_thread->status = 'open';
            $customer_thread->token = \Tools::passwdGen(12);
            $customer_thread->add();
        }

        // Create message
        $customerMessage = new \CustomerMessage();
        $customerMessage->id_customer_thread = $customerThread->id;
        $customerMessage->id_employee = 1;
        $customerMessage->message = $comment;
        $customerMessage->private = 1;

        if (!$customerMessage->add()) {
            $this->helperData->adyenLogger()->logError('An error occurred while saving the message.');
            return false;
        }

        return true;
    }
}
