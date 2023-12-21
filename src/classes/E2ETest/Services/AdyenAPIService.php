<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\BusinessLogic\AdyenAPI\Management\Connection\Http\Proxy;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\ServiceRegister;

/**
 * Class AdyenAPIService
 *
 * @package AdyenPayment\Classes\E2ETest\Services
 */
class AdyenAPIService
{
    public const MANAGEMENT_API_TEST_URL = 'management-test.adyen.com';
    public const MANAGEMENT_API_LIVE_URL = 'management-live.adyen.com';
    public const API_VERSION = 'v1';

    /**
     * Verifies if Adyen Management API is stable
     *
     * @param string $testApiKey
     * @param string $liveApiKey
     * @return void
     * @throws HttpRequestException
     */
    public function verifyManagementAPI(string $testApiKey, string $liveApiKey): void
    {
        $this->verifyTestManagementAPI($testApiKey);
        $this->verifyLiveManagementAPI($liveApiKey);
    }

    /**
     * Verifies if Adyen test Management API is stable
     *
     * @param string $apiKey
     * @return void
     * @throws HttpRequestException
     */
    private function verifyTestManagementAPI(string $apiKey): void
    {
        $proxy = new Proxy(
            static::getHttpClient(),
            self::MANAGEMENT_API_TEST_URL,
            self::API_VERSION,
            $apiKey
        );
        if (!$proxy->getApiCredentialDetails()) {
            throw new HttpRequestException('Test Management API is not stable');
        }
    }

    /**
     * Verifies if Adyen live Management API is stable
     *
     * @param string $apiKey
     * @return void
     * @throws HttpRequestException
     */
    private function verifyLiveManagementAPI(string $apiKey): void
    {
        $proxy = new Proxy(
            static::getHttpClient(),
            self::MANAGEMENT_API_LIVE_URL,
            self::API_VERSION,
            $apiKey
        );
        if (!$proxy->getApiCredentialDetails()) {
            throw new HttpRequestException('Live Management API is not stable');
        }
    }

    /**
     * Returns HttpClient instance
     *
     * @return HttpClient
     */
    protected static function getHttpClient(): HttpClient
    {
        return ServiceRegister::getService(HttpClient::class);
    }
}