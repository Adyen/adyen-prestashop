<?php

namespace Adyen\PrestaShop\service\builder;

class Payment
{
    /**
     * Add additional fields in the payments request which cannot be retrieved form the frontend
     *
     * @param $currencyIso
     * @param $formattedValue
     * @param $reference
     * @param $merchantAccount
     * @param $returnUrl
     * @param $origin
     * @param array $request
     *
     * @return array|mixed
     */
    public function buildPaymentData(
        $currencyIso,
        $formattedValue,
        $reference,
        $merchantAccount,
        $returnUrl,
        $origin,
        $request = []
    ) {
        $request['amount'] = [
            'currency' => $currencyIso,
            'value' => $formattedValue,
        ];

        $request['reference'] = $reference;
        $request['merchantAccount'] = $merchantAccount;
        $request['returnUrl'] = $returnUrl;
        $request['additionalData']['allow3DS2'] = true;
        $request['channel'] = 'web';
        $request['origin'] = $origin;
        $request['shopperInteraction'] = 'Ecommerce';

        return $request;
    }
}
