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
use OrderCore;
use PrestaShop\PrestaShop\Adapter\Entity\Address;
use PrestaShop\PrestaShop\Adapter\Entity\AddressFormat;
use PrestaShop\PrestaShop\Adapter\Entity\Carrier;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Currency;
use PrestaShop\PrestaShop\Adapter\Entity\Product;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use PrestaShopBundle\Translation\Translator;

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
     * @param $additionalData
     * @return mixed
     *
     * TODO: Refactor/modify this function w/OrderPaymentService::addPspReferenceForOrderPayment since they are related
     * TODO: Create AdditionalData class to be passed instead of an array
     */
    public function addPaymentDataToOrderFromResponse($order, $additionalData)
    {
        if (\Validate::isLoadedObject($order)) {
            // Save available data into the order_payment table
            $paymentCollection = $order->getOrderPaymentCollection();
            foreach ($paymentCollection as $payment) {
                $cardSummary = !empty($additionalData['cardSummary'])
                    ? pSQL($additionalData['cardSummary'])
                    : '****';
                $cardBin = !empty($additionalData['cardBin'])
                    ? pSQL($additionalData['cardBin'])
                    : '******';
                $paymentMethod = !empty($additionalData['paymentMethod'])
                    ? pSQL($additionalData['paymentMethod'])
                    : '';
                $expiryDate = !empty($additionalData['expiryDate'])
                    ? pSQL($additionalData['expiryDate'])
                    : '';
                $cardHolderName = !empty($additionalData['cardHolderName'])
                    ? pSQL($additionalData['cardHolderName']) : '';
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
        $templateVars = array();
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

        // If order is going from Waiting for Payment to Successful, get the orderConf email template vars in case
        // the merchant would like to send that email instead of the payment successful one
        if ($currentOrderStateId === (int)Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT') &&
            $orderStateId === Configuration::get('PS_OS_PAYMENT')) {
            $templateVars = $this->getOrderConfEmailTemplateVariables($order);
        }

        if (!$orderHistory->addWithemail(true, $templateVars)) {
            $this->logger->addError(
                'Email was not sent upon order state update',
                array("order id" => $order->id, "new state id" => $orderStateId)
            );
        }

        return $order;
    }

    /**
     * This function gets all the data shown in the default order_conf email, similar to what happens in
     * PaymentModule::validateOrder
     *
     * TODO: Check if this should be completely omitted for 1.6
     *
     * @param OrderCore $order
     * @return array
     * @throws \PrestaShopException
     * @throws LocalizationException
     */
    private function getOrderConfEmailTemplateVariables(OrderCore $order)
    {
        $currency = new Currency((int) $order->id_currency, null);
        $virtualOrder = $order->isVirtual();
        $carrier = $order->id_carrier ? new Carrier($order->id_carrier) : false;
        $invoice = new Address((int) $order->id_address_invoice);
        $delivery = new Address((int) $order->id_address_delivery);

        $data = [
            '{date}' => Tools::displayDate(date('Y-m-d H:i:s'), null, 1),
            '{payment}' => Tools::substr($order->payment, 0, 255),
            '{total_products}' => Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ?
                $order->total_products : $order->total_products_wt, $currency, false),
            '{total_discounts}' => Tools::displayPrice($order->total_discounts, $currency, false),
            '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $currency, false),
            '{total_shipping}' => Tools::displayPrice($order->total_shipping, $currency, false),
            '{total_tax_paid}' => Tools::displayPrice(($order->total_products_wt - $order->total_products) +
                ($order->total_shipping_tax_incl - $order->total_shipping_tax_excl), $currency, false),
            '{carrier}' => ($virtualOrder || !isset($carrier->name)) ? 'No carrier' : $carrier->name,
            '{delivery_block_html}' => AddressFormat::generateAddress(
                $delivery,
                array('avoid' => array()),
                '<br />',
                ' ',
                array(
                    'firstname' => '<span style="font-weight:bold;">%s</span>',
                    'lastname' => '<span style="font-weight:bold;">%s</span>',
                )
            ),
            '{invoice_block_html}' => AddressFormat::generateAddress(
                $invoice,
                array('avoid' => array()),
                '<br />',
                ' ',
                array(
                    'firstname' => '<span style="font-weight:bold;">%s</span>',
                    'lastname' => '<span style="font-weight:bold;">%s</span>',
                )
            ),

        ];

        return $data;
    }
}
