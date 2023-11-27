<?php

namespace AdyenPayment\Classes\E2ETest\Http;

use Adyen\Core\BusinessLogic\AdyenAPI\Http\Requests\HttpRequest;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\Http\HttpResponse;
use Exception;

/**
 * Class TestProxy
 *
 * @package AdyenPayment\E2ETest\Http
 */
class TestProxy
{
    /**
     * @var HttpClient
     */
    protected $httpClient;
    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var string
     */
    protected $credentials;

    /**
     * Proxy constructor.
     *
     * @param HttpClient $httpClient
     * @param string $baseUrl Shopware base url
     * @param string $credentials Authorization header credentials
     */
    public function __construct(HttpClient $httpClient, string $baseUrl, string $credentials)
    {
        $this->httpClient = $httpClient;
        $protocol = ($baseUrl === 'localhost') ? 'http://' : 'https://';
        $this->baseUrl = $baseUrl === $protocol . trim(str_replace(['http:', 'https:'], '', $baseUrl), '/');
        $this->credentials = $credentials;
    }

    /**
     * Performs GET HTTP request.
     *
     * @param HttpRequest $request
     *
     * @return HttpResponse Get HTTP response.
     *
     * @throws HttpRequestException
     */
    protected function get(HttpRequest $request): HttpResponse
    {
        return $this->call(HttpClient::HTTP_METHOD_GET, $request);
    }

    /**
     * Performs DELETE HTTP request.
     *
     * @param HttpRequest $request
     *
     * @return HttpResponse DELETE HTTP response.
     *
     * @throws HttpRequestException
     */
    protected function delete(HttpRequest $request): HttpResponse
    {
        return $this->call(HttpClient::HTTP_METHOD_DELETE, $request);
    }

    /**
     * Performs POST HTTP request.
     *
     * @param HttpRequest $request
     *
     * @return HttpResponse Response instance.
     *
     * @throws HttpRequestException
     */
    protected function post(HttpRequest $request): HttpResponse
    {
        return $this->call(HttpClient::HTTP_METHOD_POST, $request);
    }

    /**
     * Performs PUT HTTP request.
     *
     * @param HttpRequest $request
     *
     * @return HttpResponse Response instance.
     *
     * @throws HttpRequestException
     */
    protected function put(HttpRequest $request): HttpResponse
    {
        return $this->call(HttpClient::HTTP_METHOD_PUT, $request);
    }

    /**
     * Performs PATCH HTTP request.
     *
     * @param HttpRequest $request
     *
     * @return HttpResponse Response instance.
     *
     * @throws HttpRequestException
     */
    protected function patch(HttpRequest $request): HttpResponse
    {
        return $this->call(HttpClient::HTTP_METHOD_PATCH, $request);
    }

    /**
     * Performs HTTP call.
     *
     * @param string $method Specifies which http method is utilized in call.
     * @param HttpRequest $request
     *
     * @return HttpResponse Response instance.
     *
     * @throws HttpRequestException
     * @throws Exception
     */
    protected function call(string $method, HttpRequest $request): HttpResponse
    {
        $request->setHeaders(array_merge($request->getHeaders(), $this->getHeaders()));

        $url = $this->getRequestUrl($request);

        $response = $this->httpClient->request(
            $method,
            $url,
            $request->getHeaders(),
            $this->getEncodedBody($request)
        );

        $this->validateResponse($response);

        return $response;
    }

    /**
     * Retrieves request headers.
     *
     * @return array Complete list of request headers.
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'Content-Type: application/xml',
            'Accept' => 'Accept: application/json',
            'Output-Format' => 'Output-Format: JSON',
            'Authorization' => 'Authorization: Basic ' . $this->credentials,
        ];
    }

    /**
     * @param HttpRequest $request
     * @return string
     */
    protected function getEncodedBody(HttpRequest $request): string
    {
        return array_key_exists('data', $request->getBody()) ? (string)$request->getBody()['data'] : '';
    }

    /**
     * Retrieves full request url.
     *
     * @param HttpRequest $request
     *
     * @return string Full request url.
     */
    protected function getRequestUrl(HttpRequest $request): string
    {
        $sanitizedEndpoint = ltrim($request->getEndpoint(), '/');
        $url = "$this->baseUrl/$sanitizedEndpoint";

        if (!empty($request->getQueries())) {
            $url .= '?' . $this->getQueryString($request);
        }

        return $url;
    }

    /**
     * Prepares request's queries.
     *
     * @param HttpRequest $request
     *
     * @return string
     */
    protected function getQueryString(HttpRequest $request): string
    {
        return http_build_query($request->getQueries());
    }

    /**
     * Validates HTTP response.
     *
     * @param HttpResponse $response Response object to be validated.
     *
     * @throws HttpRequestException
     */
    protected function validateResponse(HttpResponse $response): void
    {
        if ($response->isSuccessful()) {
            return;
        }

        throw new HttpRequestException($response->getBody());
    }
}