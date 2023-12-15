<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class ProductTestProxy
 *
 * @package AdyenPayment\E2ETest\Http
 */
class ProductTestProxy extends TestProxy
{

    /**
     *
     * Creates request to get product data from system
     *
     * @throws HttpRequestException
     */
    public function getProductData(string $productId): array
    {
        $httpRequest = new HttpRequest("/api/products/$productId");

        return $this->get($httpRequest)->decodeBodyToArray();
    }
}