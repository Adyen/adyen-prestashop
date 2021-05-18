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

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class OrderHistory extends OrderHistoryCore
{

    /**
     * This function will ensure that all template variables sent to the order_conf template, are usable in all other
     * email templates
     *
     * @param bool $autodate
     * @param array $template_vars
     * @param Context|null $context
     * @return bool
     */
    public function addWithemail($autodate = true, $template_vars = false, Context $context = null)
    {
        // Order is reloaded because the status just changed
        $order = new Order($this->id_order);
        $context = Context::getContext();

        $invoice = new Address($order->id_address_invoice);
        $delivery = new Address($order->id_address_delivery);
        $delivery_state = $delivery->id_state ? new State((int) $delivery->id_state) : false;
        $invoice_state = $invoice->id_state ? new State((int) $invoice->id_state) : false;
        $carrier = $order->id_carrier ? new Carrier($order->id_carrier) : false;

        // If email is to be sent during notification processing, customer won't be in context
        if (is_null($context->customer)) {
            $customer = $order->getCustomer();
        } else {
            $customer = $context->customer;
        }

        $data = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{delivery_block_txt}' => $this->getFormatedAddress($delivery, "\n"),
            '{invoice_block_txt}' => $this->getFormatedAddress($invoice, "\n"),
            '{delivery_block_html}' => $this->getFormatedAddress($delivery, '<br />', array(
                'firstname' => '<span style="font-weight:bold;">%s</span>',
                'lastname' => '<span style="font-weight:bold;">%s</span>',
            )),
            '{invoice_block_html}' => $this->getFormatedAddress($invoice, '<br />', array(
                'firstname' => '<span style="font-weight:bold;">%s</span>',
                'lastname' => '<span style="font-weight:bold;">%s</span>',
            )),
            '{delivery_company}' => $delivery->company,
            '{delivery_firstname}' => $delivery->firstname,
            '{delivery_lastname}' => $delivery->lastname,
            '{delivery_address1}' => $delivery->address1,
            '{delivery_address2}' => $delivery->address2,
            '{delivery_city}' => $delivery->city,
            '{delivery_postal_code}' => $delivery->postcode,
            '{delivery_country}' => $delivery->country,
            '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
            '{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
            '{delivery_other}' => $delivery->other,
            '{invoice_company}' => $invoice->company,
            '{invoice_vat_number}' => $invoice->vat_number,
            '{invoice_firstname}' => $invoice->firstname,
            '{invoice_lastname}' => $invoice->lastname,
            '{invoice_address2}' => $invoice->address2,
            '{invoice_address1}' => $invoice->address1,
            '{invoice_city}' => $invoice->city,
            '{invoice_postal_code}' => $invoice->postcode,
            '{invoice_country}' => $invoice->country,
            '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
            '{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}' => $invoice->other,
            '{order_name}' => $order->getUniqReference(),
            '{date}' => Tools::displayDate(date('Y-m-d H:i:s'), null, 1),
            '{carrier}' => ($order->isVirtual() || !isset($carrier->name)) ?
                $this->trans('No carrier', array(), 'Admin.Payment.Notification') : $carrier->name,
            '{payment}' => Tools::substr($order->payment, 0, 255),
            '{total_paid}' => Tools::displayPrice($order->total_paid, $context->currency, false),
            '{total_products}' => Tools::displayPrice(
                Product::getTaxCalculationMethod() == PS_TAX_EXC ? $order->total_products :
                    $order->total_products_wt,
                $context->currency
            ),
            '{total_discounts}' => Tools::displayPrice($order->total_discounts, $context->currency),
            '{total_shipping}' => Tools::displayPrice($order->total_shipping, $context->currency),
            '{total_shipping_tax_excl}' => Tools::displayPrice($order->total_shipping_tax_excl, $context->currency),
            '{total_shipping_tax_incl}' => Tools::displayPrice($order->total_shipping_tax_incl, $context->currency),
            '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $context->currency),
            '{total_tax_paid}' => Tools::displayPrice(($order->total_products_wt - $order->total_products) +
                ($order->total_shipping_tax_incl - $order->total_shipping_tax_excl), $context->currency),
        );

        if (!is_array($template_vars)) {
            $template_vars = $data;
        } else {
            $template_vars = array_merge($template_vars, $data);
        }

        return parent::addWithemail($autodate, $template_vars, $context);
    }

    /**
     * @param Address $the_address that needs to be txt formatted
     * @return String the txt formated address block
     */
    protected function getFormatedAddress(Address $the_address, $line_sep, $fields_style = array())
    {
        return AddressFormat::generateAddress($the_address, array('avoid' => array()), $line_sep, ' ', $fields_style);
    }
}
