<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class CountryTestProxy
 *
 * @package AdyenPayment\Classes\E2ETest\Http
 */
class CountryTestProxy extends TestProxy
{
    /**
     * Creates request to update country
     *
     * @throws HttpRequestException
     */
    public function updateCountry(string $countryId, array $countryData): void
    {
        $httpRequest = new HttpRequest(
            "/api/countries/$countryId",
            $countryData
        );
        $this->put($httpRequest)->decodeBodyToArray();
    }

    /**
     *
     * Creates request to get country data from system
     *
     * @throws HttpRequestException
     */
    public function getCountryData(string $countryId): array
    {
        $httpRequest = new HttpRequest("/api/countries/$countryId");

        return $this->get($httpRequest)->decodeBodyToArray();
    }
}