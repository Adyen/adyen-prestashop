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
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\notification;

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\model\AdyenNotification;
use Adyen\PrestaShop\model\AdyenPaymentResponse;
use Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Context;
use Db;
use PrestaShopDatabaseException;
use PrestaShopException;
use Psr\Log\LoggerInterface;
use Adyen\PrestaShop\service\Order as OrderService;

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
     * @var AdyenPaymentResponse
     */
    private $adyenPaymentResponse;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * NotificationProcessor constructor.
     *
     * @param AdyenHelper $helperData
     * @param Db $dbInstance
     * @param OrderAdapter $orderAdapter
     * @param CustomerThreadAdapter $customerThreadAdapter
     * @param LoggerInterface $logger
     * @param Context $context
     * @param AdyenPaymentResponse $adyenPaymentResponse
     */
    public function __construct(
        AdyenHelper $helperData,
        Db $dbInstance,
        OrderAdapter $orderAdapter,
        CustomerThreadAdapter $customerThreadAdapter,
        LoggerInterface $logger,
        Context $context,
        AdyenPaymentResponse $adyenPaymentResponse,
        OrderService $orderService
    ) {
        $this->helperData = $helperData;
        $this->dbInstance = $dbInstance;
        $this->orderAdapter = $orderAdapter;
        $this->customerThreadAdapter = $customerThreadAdapter;
        $this->logger = $logger;
        $this->context = $context;
        $this->adyenPaymentResponse = $adyenPaymentResponse;
        $this->orderService = $orderService;
    }

    /**
     * @param $unprocessedNotification
     * @return bool
     */
    public function processNotification($unprocessedNotification)
    {
        // Validate if order is available by merchant reference
        $order = $this->orderAdapter->getOrderByCartId($unprocessedNotification['merchant_reference']);

        if (!\Validate::isLoadedObject($order)) {
            return false;
        }

        // Add cron message to order
        if (!$this->addMessage($unprocessedNotification)) {
            return false;
        }

        switch ($unprocessedNotification['event_code']) {
            case AdyenNotification::AUTHORISATION:
                if ('true' === $unprocessedNotification['success']) {
                    // AUTHORISATION success = true moves order to paid if order status is not PS_OS_PAYMENT
                    if ($order->getCurrentState() !== \Configuration::get('PS_OS_PAYMENT')) {
                        $order->setCurrentState(\Configuration::get('PS_OS_PAYMENT'));
                    }

                    $this->orderService->addPaymentDataToOrderFromResponse($order, $unprocessedNotification);
                } else {
                    if ($order->getCurrentState() !== \Configuration::get('PS_OS_CANCELED')) {
                        // No previous authorisation notification was processed before
                        if (!$this->hasProcessedAuthorisationSuccessNotification(
                            $unprocessedNotification['merchant_reference']
                        )) {
                            // AUTHORISATION success = false moves order to canceled if not canceled already
                            $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));
                        } else {
                            $this->logger->addAdyenNotification('Notification with entity_id (' .
                                $unprocessedNotification['entity_id'] . ') was ignored during processing the ' .
                                'notifications because another Authorisation success = true notification has already ' .
                                'been processed for the same order.');
                        }
                    }
                }

                $this->adyenPaymentResponse->deletePaymentResponseByCartId($unprocessedNotification);
                break;
            case AdyenNotification::OFFER_CLOSED:
                if ('true' === $unprocessedNotification['success']) {
                    // OFFER_CLOSED success = true moves order to canceled if order status is
                    // ADYEN_OS_WAITING_FOR_PAYMENT
                    if ($order->getCurrentState() === \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT')) {
                        $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));
                    }
                }

                $this->adyenPaymentResponse->deletePaymentResponseByCartId($unprocessedNotification);
                break;
        }

        return true;
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
        if ((!empty($notification['reason']))) {
            $success = $successResult . PHP_EOL . "reason:" . $notification['reason'] . PHP_EOL;
        } else {
            $success = $successResult . PHP_EOL;
        }

        $type = 'Adyen HTTP Notification(s):';
        $comment = sprintf(
            '%s ' . PHP_EOL . 'eventCode: %s ' . PHP_EOL . ' pspReference: %s ' . PHP_EOL .
            ' paymentMethod: %s ' . PHP_EOL . ' success: %s',
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
            $this->logger->error(sprintf(
                "Order with id: \"%s\" cannot be found while notification with id: \"%s\" was processed.",
                $notification['merchant_reference'],
                $notification['entity_id']
            ));
            return false;
        }

        // Find customer by order id
        $customer = $order->getCustomer();
        if (empty($customer)) {
            $this->logger->error(sprintf(
                "Customer with id: \"%s\" cannot be found for order with id: \"%s\" while notification with id:" .
                " \"%s\" was processed.",
                $order->id_customer,
                $order->id,
                $notification['entity_id']
            ));
            return false;
        }

        // Find customer thread by order id and customer email
        $customerThread = $this->customerThreadAdapter->getCustomerThreadByEmailAndOrderId(
            $customer->email,
            $order->id
        );

        if (empty($customerThread->id)) {
            $customerThread = new \CustomerThread();
            $customerThread->id_contact = 0;
            $customerThread->id_customer = (int)$customer->id;
            $customerThread->id_shop = (int)$this->context->shop->id;
            $customerThread->id_order = (int)$order->id;
            $customerThread->id_lang = (int)$this->context->language->id;
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

    /**
     * Returns true if an Authorisation notification with success = true has already been processed before for the same
     * merchant_reference
     *
     * @param $merchantReference
     * @return bool
     */
    private function hasProcessedAuthorisationSuccessNotification($merchantReference)
    {
        $notificationModel = new AdyenNotification();

        $processedNotifications = $notificationModel->getProcessedNotificationsByMerchantReference($merchantReference);

        if (empty($processedNotifications)) {
            return false;
        }

        foreach ($processedNotifications as $processedNotification) {
            if (AdyenNotification::AUTHORISATION === $processedNotification['event_code'] &&
                'true' === $processedNotification['success']
            ) {
                return true;
            }
        }

        return false;
    }
}
