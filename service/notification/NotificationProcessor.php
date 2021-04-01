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
use Adyen\PrestaShop\service\OrderPaymentService;
use Adyen\Util\Currency;
use Context;
use Db;
use OrderCore;
use OrderPayment;
use PrestaShopDatabaseException;
use PrestaShopException;
use Psr\Log\LoggerInterface;
use Adyen\PrestaShop\service\Order as OrderService;

class NotificationProcessor
{
    /**
     * Order statuses which can be overwritten by an authorised success = true notification
     * In case an order is canceled or waiting for payment, an authorisation notification can bump the order status to
     * paid, but in case it's already paid or under preparation it should not change it's order status
     *
     * @var string[]
     */
    private static $nonFinalOrderStatuses = array(
        'PS_OS_CANCELED',
        'PS_OS_ERROR',
        'ADYEN_OS_WAITING_FOR_PAYMENT'
    );

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
     * @var Currency
     */
    private $utilCurrency;

    /**
     * @var OrderPaymentService
     */
    private $orderPaymentService;

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
     * @param OrderService $orderService
     * @param Currency $utilCurrency
     * @param OrderPaymentService $orderPaymentService
     */
    public function __construct(
        AdyenHelper $helperData,
        Db $dbInstance,
        OrderAdapter $orderAdapter,
        CustomerThreadAdapter $customerThreadAdapter,
        LoggerInterface $logger,
        Context $context,
        AdyenPaymentResponse $adyenPaymentResponse,
        OrderService $orderService,
        Currency $utilCurrency,
        OrderPaymentService $orderPaymentService
    ) {
        $this->helperData = $helperData;
        $this->dbInstance = $dbInstance;
        $this->orderAdapter = $orderAdapter;
        $this->customerThreadAdapter = $customerThreadAdapter;
        $this->logger = $logger;
        $this->context = $context;
        $this->adyenPaymentResponse = $adyenPaymentResponse;
        $this->orderService = $orderService;
        $this->utilCurrency = $utilCurrency;
        $this->orderPaymentService = $orderPaymentService;
    }

    /**
     * @param $unprocessedNotification
     * @return bool
     * @throws \Exception
     */
    public function processNotification($unprocessedNotification)
    {
        // Validate if order is available by merchant reference
        /* @var OrderCore $order */
        $order = $this->orderAdapter->getOrderByCartId($unprocessedNotification['merchant_reference']);

        if (!\Validate::isLoadedObject($order)) {
            return false;
        }

        // Add cron message to order
        if (!$this->addMessage($unprocessedNotification)) {
            return false;
        }

        // Ignore notification when the order was paid using another payment module
        if ($order->module !== 'adyenofficial') {
            $this->logger->addAdyenNotification(
                'Notification with entity_id (' .
                $unprocessedNotification['entity_id'] . ') was ignored during processing the ' .
                'notifications because the order was NOT paid using the "adyenofficial" Adyen payment module'
            );

            // Update the notification as done, no need to retry processing it
            return true;
        }

        // Process notifications based on it's event code
        switch ($unprocessedNotification['event_code']) {
            case AdyenNotification::AUTHORISATION:
                if ('true' === $unprocessedNotification['success']) {
                    // If notification data does not match cart and order, set to PAYMENT_NEEDS_ATTENTION
                    if (!$this->validateWithCartAndOrder($unprocessedNotification, $order)) {
                        $this->orderService->updateOrderState(
                            $order,
                            \Configuration::get('ADYEN_OS_PAYMENT_NEEDS_ATTENTION')
                        );
                        $this->orderService->addPaymentDataToOrderFromResponse(
                            $order,
                            unserialize($unprocessedNotification['additional_data'])
                        );

                        return true;
                    }

                    // If not in a final status, set to PAYMENT
                    if ($this->isCurrentOrderStatusANonFinalStatus($order->getCurrentState())) {
                        $this->orderService->updateOrderState($order, \Configuration::get('PS_OS_PAYMENT'));
                    }

                    $this->setPspReferenceUsingNotificationData($order, $unprocessedNotification);

                    // Add additional data to order if there is any (only possible when the notification success is
                    // true
                    $this->orderService->addPaymentDataToOrderFromResponse(
                        $order,
                        unserialize($unprocessedNotification['additional_data'])
                    );
                } else { // Notification success is 'false'
                    // Order state is not canceled yet
                    if ($order->getCurrentState() !== \Configuration::get('PS_OS_CANCELED')) {
                        // If order has a non final status, set to cancelled
                        if ($this->isCurrentOrderStatusANonFinalStatus($order->getCurrentState())) {
                            // Moves order to canceled
                            $this->orderService->updateOrderState(
                                $order,
                                \Configuration::get('PS_OS_CANCELED')
                            );

                            $this->setPspReferenceUsingNotificationData($order, $unprocessedNotification);
                        } else {
                            // Add this log when the notification is ignore because an authorisation success true
                            // notification has already been processed for the same order
                            $this->logger->addAdyenNotification(
                                'Notification with entity_id (' .
                                $unprocessedNotification['entity_id'] . ') was ignored during processing ' .
                                'because the order status is already in a final state.'
                            );
                        }
                    }
                }

                $this->adyenPaymentResponse->deletePaymentResponseByCartId(
                    $unprocessedNotification['merchant_reference']
                );

                break;
            case AdyenNotification::OFFER_CLOSED:
                // Notification success is 'true' AND current status is ADYEN_OS_WAITING_FOR_PAYMENT
                if ('true' === $unprocessedNotification['success']) {
                    // Moves order to canceled if order status is waiting for payment
                    if ($order->getCurrentState() === \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT')) {
                        $this->orderService->updateOrderState(
                            $order,
                            \Configuration::get('PS_OS_CANCELED')
                        );

                        $this->setPspReferenceUsingNotificationData($order, $unprocessedNotification);
                    }
                }

                $this->adyenPaymentResponse->deletePaymentResponseByCartId(
                    $unprocessedNotification['merchant_reference']
                );

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
            $this->logger->error(
                sprintf(
                    "Order with id: \"%s\" cannot be found while notification with id: \"%s\" was processed.",
                    $notification['merchant_reference'],
                    $notification['entity_id']
                )
            );
            return false;
        }

        // Find customer by order id
        $customer = $order->getCustomer();
        if (empty($customer)) {
            $this->logger->error(
                sprintf(
                    "Customer with id: \"%s\" cannot be found for order with id: \"%s\" while notification with id:" .
                    " \"%s\" was processed.",
                    $order->id_customer,
                    $order->id,
                    $notification['entity_id']
                )
            );
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
     * Checks if the current order status is in the self::$nonFinalOrderStatuses list
     *
     * @param string $currentOrderStatus
     * @return bool
     */
    private function isCurrentOrderStatusANonFinalStatus($currentOrderStatus)
    {
        foreach (self::$nonFinalOrderStatuses as $nonFinalOrderStatus) {
            if ($currentOrderStatus === \Configuration::get($nonFinalOrderStatus)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $notification
     * @param OrderCore $order
     * @return bool
     * @throws \Exception
     */
    private function validateWithCartAndOrder($notification, OrderCore $order)
    {
        $cart = \Cart::getCartByOrderId($order->id);

        if (!\Validate::isLoadedObject($cart)) {
            $this->logger->addAdyenNotification(
                sprintf(
                    'Unable to load cart object linked to Order (%s) and Notification (%s)',
                    $order->id,
                    $notification['entity_id']
                )
            );

            return false;
        }

        $cartCurrency = \Currency::getCurrency($cart->id_currency);
        $cartCurrencyIso = $cartCurrency['iso_code'];

        $cartTotalMinorUnits = $this->utilCurrency->sanitize($cart->getOrderTotal(), $cartCurrencyIso);

        if ($notification['amount_currency'] !== $cartCurrencyIso ||
            (int)$notification['amount_value'] !== $cartTotalMinorUnits) {
            $this->logger->addAdyenNotification(
                sprintf(
                    'Notification: id (%s), amount (%s) and currency (%s) contains an incompatible ' .
                    'amount/currency with Cart: id (%s), amount (%s) and currency (%s).',
                    $notification['entity_id'],
                    $notification['amount_value'],
                    $notification['amount_currency'],
                    $cart->id,
                    $cartTotalMinorUnits,
                    $cartCurrencyIso
                )
            );

            return false;
        }

        return true;
    }

    /**
     * @param OrderCore $order
     * @param $notification
     * @return false|OrderPayment|null
     * @throws PrestaShopException
     */
    private function setPspReferenceUsingNotificationData(OrderCore $order, $notification)
    {
        $orderPayment = $this->orderPaymentService->getAdyenOrderPayment($order);

        // Update transaction_id with the original psp reference if available in the notification
        if ($orderPayment) {
            if (!empty($notification['original_reference'])) {
                $pspReference = $notification['original_reference'];
            } else {
                $pspReference = $notification['pspreference'];
            }

            $orderPayment = $this->orderPaymentService->addPspReferenceForOrderPayment(
                $orderPayment,
                $pspReference
            );
        }

        return $orderPayment;
    }
}
