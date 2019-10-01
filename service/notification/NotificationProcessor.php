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

        $this->context = \Context::getContext();
    }

    /**
     *
     */
    public function doPostProcess()
    {
        $unprocessedNotifications = $this->getUnprocessedNotifications();

        foreach ($unprocessedNotifications as $unprocessedNotification) {
            // update as processing
            $this->updateNotificationAsProcessing($unprocessedNotification['entity_id']);

            // Add cron message to order
            if ($this->addMessage($unprocessedNotification)) {
                // processing is done
                $this->updateNotificationAsDone($unprocessedNotification['entity_id']);
            } else {
                // processing had some error
                $this->updateNotificationAsNew($unprocessedNotification['entity_id']);
            }
        }
    }

    /**
     * @return array|bool|null|object
     */
    protected function getUnprocessedNotifications()
    {
        $dateStart = new \DateTime();
        $dateStart->modify('-1 day');

        $dateEnd = new \DateTime();
        $dateEnd->modify('-1 minute');

        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'adyen_notification'
            . ' WHERE `done` = 0'
            . ' AND `processing` = 0'
            . ' AND `created_at` > "' . $dateStart->format('Y-m-d H:i:s') . '"'
            . ' AND `created_at` < "' . $dateEnd->format('Y-m-d H:i:s'). '"'
            . ' LIMIT 100';

        return $this->dbInstance->executeS($sql);
    }

    /**
     * Update the unprocessed and not done notification to processing
     * @param $id
     * @return mixed
     */
    protected function updateNotificationAsProcessing($id)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'adyen_notification'
            . ' SET `processing` = 1'
            . ' WHERE `done` = 0'
            . ' AND `processing` = 0'
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
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'adyen_notification'
            . ' SET `processing` = 0, `done` = 1'
            . ' WHERE `done` = 0'
            . ' AND `processing` = 1'
            . ' AND `entity_id` = "' . (int)$id . '"';
        return $this->dbInstance->execute($sql);
    }

    /**
     * Update the processed but not done notification to new
     * @param $id
     * @return mixed
     */
    protected function updateNotificationAsNew($id)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'adyen_notification'
            . ' SET `processing` = 0, `done` = 0'
            . ' WHERE `done` = 0'
            . ' AND `processing` = 1'
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
            '%s ' . PHP_EOL . 'eventCode: %s ' . PHP_EOL . ' pspReference: %s ' . PHP_EOL . ' paymentMethod: %s ' . PHP_EOL .
            ' success: %s',
            $type,
            $notification['event_code'],
            $notification['pspreference'],
            $notification['payment_method'],
            $success
        );

        if ($this->helperData->isPrestashop16()) {
            $orderId = \Order::getOrderByCartId($notification['merchant_reference']);
            if ($orderId) {
                $order = new \Order($orderId);
            }
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
            $customerThread = new \CustomerThread();
            $customerThread->id_contact = 0;
            $customerThread->id_customer = (int) $customer->id;
            $customerThread->id_shop = (int) $this->context->shop->id;
            $customerThread->id_order = (int) $order->id;
            $customerThread->id_lang = (int) $this->context->language->id;
            $customerThread->email = $customer->email;
            $customerThread->status = 'open';
            $customerThread->token = \Tools::passwdGen(12);
            $customerThread->add();
        }

        // Create message
        $customerMessage = new \CustomerMessage();
        $customerMessage->id_customer_thread = $customerThread->id;
        // employee cannot be empty - default employee id is 1
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
