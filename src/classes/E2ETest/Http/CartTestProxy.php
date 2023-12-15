<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class CustomerTestProxy
 *
 * @package AdyenPayment\E2ETest\Http
 */
class CartTestProxy extends TestProxy
{
    /**
     * Creates request to create cart in database
     *
     * @param array $cartData
     * @return array
     * @throws HttpRequestException
     */
    public function createCart(array $cartData): array
    {
        $httpRequest = new HttpRequest(
            "/api/carts",
            $cartData
        );

        return $this->post($httpRequest)->decodeBodyToArray();
    }
}