<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class ShopsTestProxy
 *
 * @package AdyenPayment\E2ETest\Http
 */
class ShopsTestProxy extends TestProxy
{

    /**
     * Creates request to update subStore
     *
     * @throws HttpRequestException
     */
    public function updateDefaultShopName(array $subStoreData): void
    {
        $httpRequest = new HttpRequest("/api/shops", $subStoreData);
        $this->put($httpRequest)->decodeBodyToArray();
    }

    /**
     * Creates request to update subStore url
     *
     * @throws HttpRequestException
     */
    public function updateShopUrl(array $subStoreData): void
    {
        $httpRequest = new HttpRequest("/api/shop_urls", $subStoreData);
        $this->put($httpRequest)->decodeBodyToArray();
    }

    /**
     *
     * Creates request to get exist subStores from system
     *
     * @throws HttpRequestException
     */
    public function getSubStores(): array
    {
        $httpRequest = new HttpRequest("/api/shops");

        return $this->get($httpRequest)->decodeBodyToArray();
    }

    /**
     *
     * Creates request to get existing subStore urls from system
     *
     * @throws HttpRequestException
     */
    public function getSubStoreUrls(): array
    {
        $httpRequest = new HttpRequest("/api/shop_urls");

        return $this->get($httpRequest)->decodeBodyToArray();
    }

    /**
     *
     * Creates request to add new subStore
     *
     * @throws HttpRequestException
     */
    public function createSubStore(array $subStoreData): void
    {
        $httpRequest = new HttpRequest("/api/shops", $subStoreData);
        $this->post($httpRequest)->decodeBodyToArray();
    }

    /**
     *
     * Creates request to add new subStore url
     *
     * @throws HttpRequestException
     */
    public function createShopUrl(array $subStoreUrl): void
    {
        $httpRequest = new HttpRequest("/api/shop_urls", $subStoreUrl);
        $this->post($httpRequest)->decodeBodyToArray();
    }
}