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
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\notification;

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Context;
use Db;
use PrestaShopDatabaseException;
use PrestaShopException;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderAdapter
     */
    private $orderAdapter;

    /**
     * @var CustomerThreadAdapter
     */
    private $customerThreadAdapter;

    /**
     * @var Context
     */
    private $context;

    /**
     * NotificationProcessor constructor.
     *
     * @param AdyenHelper $helperData
     * @param Db $dbInstance
     * @param OrderAdapter $orderAdapter
     * @param CustomerThreadAdapter $customerThreadAdapter
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        AdyenHelper $helperData,
        Db $dbInstance,
        OrderAdapter $orderAdapter,
        CustomerThreadAdapter $customerThreadAdapter,
        LoggerInterface $logger,
        Context $context
    ) {
        $this->helperData = $helperData;
        $this->dbInstance = $dbInstance;
        $this->orderAdapter = $orderAdapter;
        $this->customerThreadAdapter = $customerThreadAdapter;
        $this->logger = $logger;
        $this->context = $context;
    }

    /**
     * @return array|bool|null|object
     */
    public function getUnprocessedNotifications()
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
    public function updateNotificationAsProcessing($id)
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
    public function updateNotificationAsDone($id)
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
    public function updateNotificationAsNew($id)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'adyen_notification'
            . ' SET `processing` = 0, `done` = 0'
            . ' WHERE `done` = 0'
            . ' AND `processing` = 1'
            . ' AND `entity_id` = "' . (int)$id . '"';
        return $this->dbInstance->execute($sql);
    }

    /**
     * Add order message based on processing the notification
     *
     * @param $notification
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addMessage($notification)
    {
        $successResult = (strcmp($notification['success'], 'true') == 0 ||
            strcmp($notification['success'], '1') == 0) ? 'true' : 'false';
        $success = (!empty($notification['reason'])) ? "$successResult" . PHP_EOL . "reason:" . $notification['reason'] . PHP_EOL : $successResult . PHP_EOL;

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

        if ($notification['event_code'] == 'REFUND') {
            $order = $this->orderAdapter->getOrderByOrderSlipId($notification['merchant_reference']);
        } else {
            $order = $this->orderAdapter->getOrderByCartId($notification['merchant_reference']);
        }

        if (empty($order)) {
            $this->logger->error('Order with id: "' . $notification['merchant_reference'] . '" cannot be found while notification with id: "' . $notification['entity_id'] . '" was processed.');
            return false;
        }

        // Find customer by order id
        $customer = $order->getCustomer();
        if (empty($customer)) {
            $this->logger->error('Customer with id: "' . $order->id_customer . '" cannot be found for order with id: "' . $order->id . '" while notification with id: "' . $notification['entity_id'] . '" was processed.');
            return false;
        }

        // Find customer thread by order id and customer email
        $customerThread = $this->customerThreadAdapter->getCustomerThreadByEmailAndOrderId($customer->email, $order->id);

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
            $this->logger->error('An error occurred while saving the message.');
            return false;
        }

        return true;
    }
}
