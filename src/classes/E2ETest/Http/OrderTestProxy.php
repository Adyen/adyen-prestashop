<?php
namespace AdyenPayment\Classes\E2ETest\Http;


use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class ProductTestProxy
 *
 * @package AdyenPayment\E2ETest\Http
 */
class OrderTestProxy extends TestProxy
{

    /**
     * Creates request to create order in database
     *
     * @param array $orderData
     * @return array
     * @throws HttpRequestException
     */
    public function createOrder(array $orderData): array
    {
        $httpRequest = new HttpRequest(
            "/api/orders",
            $orderData
        );

        return $this->post($httpRequest)->decodeBodyToArray();
    }
    /**
     * Creates request to update order in database
     *
     * @param array $orderData
     * @return array
     * @throws HttpRequestException
     */
    public function updateOrder(array $orderData): array
    {
        $httpRequest = new HttpRequest(
            "/api/orders",
            $orderData
        );

        return $this->put($httpRequest)->decodeBodyToArray();
    }
}