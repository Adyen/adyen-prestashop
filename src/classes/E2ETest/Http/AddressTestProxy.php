<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class AddressTestProxy
 */
class AddressTestProxy extends TestProxy
{
    /**
     * Creates request to create address in database
     *
     * @throws HttpRequestException
     */
    public function createAddress(array $addressData): void
    {
        $httpRequest = new HttpRequest(
            '/api/addresses',
            $addressData
        );
        $this->post($httpRequest)->decodeBodyToArray();
    }
}
