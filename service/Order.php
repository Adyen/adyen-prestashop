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

namespace Adyen\PrestaShop\service;

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

class Order
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * Order constructor.
     */
    public function __construct()
    {
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
    }

    /**
     * Add the payment data received from a response to the order_payments linked to the order
     *
     * @param $order
     * @param $response
     * @return mixed
     *
     * TODO: Refactor/modify this function w/OrderPaymentService::addPspReferenceForOrderPayment since they are related
     */
    public function addPaymentDataToOrderFromResponse($order, $response)
    {
        if (\Validate::isLoadedObject($order)) {
            // Save available data into the order_payment table
            $paymentCollection = $order->getOrderPaymentCollection();
            foreach ($paymentCollection as $payment) {
                $cardSummary = !empty($response['additionalData']['cardSummary'])
                    ? pSQL($response['additionalData']['cardSummary'])
                    : '****';
                $cardBin = !empty($response['additionalData']['cardBin'])
                    ? pSQL($response['additionalData']['cardBin'])
                    : '******';
                $paymentMethod = !empty($response['additionalData']['paymentMethod'])
                    ? pSQL($response['additionalData']['paymentMethod'])
                    : 'Adyen';
                $expiryDate = !empty($response['additionalData']['expiryDate'])
                    ? pSQL($response['additionalData']['expiryDate'])
                    : '';
                $cardHolderName = !empty($response['additionalData']['cardHolderName'])
                    ? pSQL($response['additionalData']['cardHolderName']) : '';
                $payment->card_number = $cardBin . ' *** ' . $cardSummary;
                $payment->card_brand = $paymentMethod;
                $payment->card_expiration = $expiryDate;
                $payment->card_holder = $cardHolderName;
                $payment->save();
            }
        }

        return $order;
    }

    /**
     * @param $order
     * @param $orderStateId
     *
     * @return bool
     */
    public function updateOrderState($order, $orderStateId)
    {
        // check if the new order state is the same as the current state
        $currentOrderStateId = (int)$order->getCurrentState();

        if ($currentOrderStateId === $orderStateId) {
            // duplicate order state handling, no need to update the order
            return false;
        }

        // Change order history in case the order updates to a new order state
        $orderHistory = new \OrderHistory();
        $orderHistory->id_order = $order->id;

        $useExistingPayment = !$order->hasInvoice();

        $orderHistory->changeIdOrderState(
            $orderStateId,
            $order->id,
            $useExistingPayment
        );

        if (!$orderHistory->addWithemail()) {
            $this->logger->addError(
                'Email was not sent upon order state update',
                array("order id" => $order->id, "new state id" => $orderStateId)
            );
        }

        return $order;
    }
}
