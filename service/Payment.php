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
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service;

class Payment
{
    const AFTERPAY_PAYMENT_METHOD = 'afterpay';
    const RATEPAY_PAYMENT_METHOD = 'ratepay';
    const KLARNA_PAYMENT_METHOD = 'klarna';
    const KLARNA_B2B_PAYMENT_METHOD = 'klarna_b2b';
    const KLARNA_ACCOUNT_PAYMENT_METHOD = 'klarna_account';
    const KLARNA_PAYNOW_PAYMENT_METHOD = 'klarna_paynow';

    /**
     * List of open invoice payment methods
     *
     * @var array
     */
    private static $openInvoicePaymentMethods = array(
        self::AFTERPAY_PAYMENT_METHOD,
        self::RATEPAY_PAYMENT_METHOD,
        self::KLARNA_PAYMENT_METHOD,
        self::KLARNA_B2B_PAYMENT_METHOD,
        self::KLARNA_ACCOUNT_PAYMENT_METHOD,
        self::KLARNA_PAYNOW_PAYMENT_METHOD
    );

    /**
     * Returns true if the parameter is a valid open invoice payment method
     *
     * @param $paymentMethod
     * @return bool
     */
    public function isOpenInvoicePaymentMethod($paymentMethod)
    {
        if (in_array(strtolower($paymentMethod), self::$openInvoicePaymentMethods)) {
            return true;
        }

        return false;
    }
}
