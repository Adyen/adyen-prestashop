<?php

namespace AdyenPayment\Classes\Proxies;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Proxy;
use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;

/**
 * Class GithubProxy
 *
 * @package AdyenPayment\Classes\Proxies
 */
class GithubProxy extends Proxy
{
    /**
     * Retrieves latest version from gitHub.
     *
     * @return string
     *
     * @throws HttpRequestException
     */
    public function getLatestVersion(): string
    {
        $response = $this->get(new HttpRequest('repos/Adyen/adyen-prestashop/releases/latest'));
        $body = $response->decodeBodyToArray();

        return $body['tag_name'] ?? '';
    }

    protected function getRequestUrl(HttpRequest $request): string
    {
        $sanitizedEndpoint = ltrim($request->getEndpoint(), '/');

        return "$this->baseUrl/$sanitizedEndpoint";
    }
}