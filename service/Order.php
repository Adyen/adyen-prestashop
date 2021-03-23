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
    }

    /**
     * @param \OrderCore $order
     * @param string $pspReference
     */
    public function addPspReferenceForOrderPayment($order, $pspReference)
    {
        if (\Validate::isLoadedObject($order)) {
            $paymentCollection = $order->getOrderPaymentCollection();

            // get first transaction
            $payment = $paymentCollection[0];
            $payment->transaction_id = $pspReference;
            $payment->save();
        }
    }

    /**
     * @param \OrderCore $order
     * @return mixed|null
     */
    public function getPspReferenceForOrderPayment($order)
    {
        if (\Validate::isLoadedObject($order)) {
            $paymentCollection = $order->getOrderPaymentCollection();

            // get first transaction
            $payment = $paymentCollection[0];
            return $payment->transaction_id;
        }

        return null;
    }

    /**
     * @param $order
     * @param $orderStateId
     * @param $extraVars
     *
     * @return bool
     */
    public function updateOrderState($order, $orderStateId, $extraVars)
    {
        // check if the new order state is the same as the current state
        $currentOrderStateId = (int) $order->getCurrentState();

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
            $this->logger->addError('Email was not sent upon order state update', ["order id" => $order->id, "new state id" => $orderStateId]);
        }

        $orderPaymentCollection = $order->getOrderPaymentCollection();
        // TODO check if order payment is Adyen $orderPaymentCollection->where('payment_method', '=', 'Adyen');

        /** @var \OrderPayment[] $orderPayments */
        $orderPayments = $orderPaymentCollection->getAll();
        foreach ($orderPayments as $orderPayment) {
            if (\Validate::isLoadedObject($orderPayment)) {
                if (empty($orderPayment->transaction_id) && !empty($extraVars['transaction_id'])) {
                    $orderPayment->transaction_id = $extraVars['transaction_id'];
                    $orderPayment->save();
                }
            }
        }
    }
}
