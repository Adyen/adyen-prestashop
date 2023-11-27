<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class CustomerTestProxy
 *
 * @package AdyenPayment\E2ETest\Http
 */
class CustomerTestProxy extends TestProxy
{
    /**
     * Creates request to create customer in database
     *
     * @throws HttpRequestException
     */
    public function createCustomer(array $customerData): void
    {
        $httpRequest = new HttpRequest(
            "/api/customers",
            $customerData
        );
        $this->post($httpRequest)->decodeBodyToArray();
    }
}