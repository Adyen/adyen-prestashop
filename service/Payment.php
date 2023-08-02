<?php

namespace Adyen\PrestaShop\service;

class Payment
{
    public const AFTERPAY_PAYMENT_METHOD = 'afterpay';
    public const AFTERPAY_B2B_PAYMENT_METHOD = 'afterpay_b2b';
    public const AFTERPAY_DEFAULT_PAYMENT_METHOD = 'afterpay_default';
    public const AFTERPAY_DIRECTDEBIT_PAYMENT_METHOD = 'afterpay_directdebit';

    public const AFTERPAYTOUCH_PAYMENT_METHOD = 'afterpaytouch';

    public const RATEPAY_PAYMENT_METHOD = 'ratepay';
    public const RATEPAY_DIRECTDEBIT_PAYMENT_METHOD = 'ratepay_directdebit';

    public const KLARNA_PAYMENT_METHOD = 'klarna';
    public const KLARNA_B2B_PAYMENT_METHOD = 'klarna_b2b';
    public const KLARNA_PAYNOW_PAYMENT_METHOD = 'klarna_paynow';
    public const KLARNA_ACCOUNT_PAYMENT_METHOD = 'klarna_account';
    public const CLEARPAY_PAYMENT_METHOD = 'clearpay';

    /**
     * List of open invoice payment methods
     *
     * @var array
     */
    private static $openInvoicePaymentMethods = [
        self::AFTERPAY_PAYMENT_METHOD,
        self::AFTERPAY_B2B_PAYMENT_METHOD,
        self::AFTERPAY_DEFAULT_PAYMENT_METHOD,
        self::AFTERPAY_DIRECTDEBIT_PAYMENT_METHOD,
        self::AFTERPAYTOUCH_PAYMENT_METHOD,
        self::RATEPAY_PAYMENT_METHOD,
        self::RATEPAY_DIRECTDEBIT_PAYMENT_METHOD,
        self::KLARNA_PAYMENT_METHOD,
        self::KLARNA_B2B_PAYMENT_METHOD,
        self::KLARNA_PAYNOW_PAYMENT_METHOD,
        self::KLARNA_ACCOUNT_PAYMENT_METHOD,
        self::CLEARPAY_PAYMENT_METHOD
    ];

    /**
     * Returns true if the parameter is a valid open invoice payment method
     *
     * @param $paymentMethod
     *
     * @return bool
     */
    public function isOpenInvoicePaymentMethod($paymentMethod)
    {
        if (in_array(\Tools::strtolower($paymentMethod), self::$openInvoicePaymentMethods)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if $paymentMethod is 'afterpaytouch'
     *
     * @param $paymentMethod
     *
     * @return bool
     */
    public function isAfterPayTouchPaymentMethod($paymentMethod)
    {
        if (self::AFTERPAYTOUCH_PAYMENT_METHOD === $paymentMethod) {
            return true;
        }

        return false;
    }
}
