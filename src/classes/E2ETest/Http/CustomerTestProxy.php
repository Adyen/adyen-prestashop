<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class CustomerTestProxy
 */
class CustomerTestProxy extends TestProxy
{
    /**
     * Creates request to create customer in database
     *
     * @param array $customerData
     *
     * @return array
     *
     * @throws HttpRequestException
     */
    public function createCustomer(array $customerData): array
    {
        $httpRequest = new HttpRequest(
            '/api/customers',
            $customerData
        );

        return $this->post($httpRequest)->decodeBodyToArray();
    }
}
