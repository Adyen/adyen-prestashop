<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class CurrencyTestProxy
 */
class CurrencyTestProxy extends TestProxy
{
    /**
     * Creates request to create currency
     *
     * @param array $currencyData
     *
     * @return array
     *
     * @throws HttpRequestException
     */
    public function createCurrency(array $currencyData): array
    {
        $httpRequest = new HttpRequest(
            '/api/currencies',
            $currencyData
        );

        return $this->post($httpRequest)->decodeBodyToArray();
    }

    /**
     * Creates request to update currency
     *
     * @throws HttpRequestException
     */
    public function updateCurrency(string $currencyId, array $currencyData): void
    {
        $httpRequest = new HttpRequest(
            "/api/currencies/$currencyId",
            $currencyData
        );
        $this->put($httpRequest)->decodeBodyToArray();
    }

    /**
     * Creates request to get currency data from system
     *
     * @throws HttpRequestException
     */
    public function getCurrencyData(string $currencyId): array
    {
        $httpRequest = new HttpRequest("/api/currencies/$currencyId");

        return $this->get($httpRequest)->decodeBodyToArray();
    }
}
