<?php

namespace AdyenPayment\Classes;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\PaymentMethod;

class SurchargeCalculator
{
    /**
     * @param PaymentMethod $paymentMethod
     * @param string $conversionRate
     * @param Amount $amount
     *
     * @return float|int
     */
    public static function calculateSurcharge(PaymentMethod $paymentMethod, string $conversionRate, Amount $amount)
    {
        $surchargeType = $paymentMethod->getSurchargeType();
        $fixedAmount = (float) $paymentMethod->getFixedSurcharge() * (float) $conversionRate;
        $limit = (float) $paymentMethod->getSurchargeLimit() * (float) $conversionRate;
        $percent = $paymentMethod->getPercentSurcharge();

        if ($surchargeType === 'fixed') {
            return $fixedAmount;
        }

        if ($surchargeType === 'percent') {
            $surchargeAmount = $amount->getPriceInCurrencyUnits() / 100 * $percent;

            return $limit && $surchargeAmount > $limit ? $limit : $surchargeAmount;
        }

        if ($surchargeType === 'combined') {
            $surchargeAmount = $amount->getPriceInCurrencyUnits() / 100 * $percent;

            return $limit ? (min($surchargeAmount + $fixedAmount, $limit)) : $surchargeAmount + $fixedAmount;
        }

        return 0;
    }
}
