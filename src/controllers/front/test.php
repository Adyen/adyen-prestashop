<?php

use Adyen\Core\BusinessLogic\AdyenAPI\Exceptions\ConnectionSettingsNotFoundException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ApiCredentialsDoNotExistException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ApiKeyCompanyLevelException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyConnectionDataException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyStoreException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidAllowedOriginException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidApiKeyException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidConnectionSettingsException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidModeException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\MerchantIdChangedException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ModeChangedException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\UserDoesNotHaveNecessaryRolesException;
use Adyen\Core\BusinessLogic\Domain\Merchant\Exceptions\ClientKeyGenerationFailedException;
use Adyen\Core\BusinessLogic\Domain\Payment\Exceptions\PaymentMethodDataEmptyException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\FailedToGenerateHmacException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\FailedToRegisterWebhookException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Exceptions\MerchantDoesNotExistException;
use Adyen\Core\BusinessLogic\E2ETest\Services\AdyenAPIService;
use Adyen\Core\BusinessLogic\E2ETest\Services\CreateIntegrationDataService;
use Adyen\Core\BusinessLogic\E2ETest\Services\TransactionLogService;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Webhook\Receiver\HmacSignature;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\E2ETest\Exception\InvalidDataException;
use AdyenPayment\Classes\E2ETest\Http\AddressTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CartTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CountryTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CurrencyTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CustomerTestProxy;
use AdyenPayment\Classes\E2ETest\Http\OrderTestProxy;
use AdyenPayment\Classes\E2ETest\Http\ProductTestProxy;
use AdyenPayment\Classes\E2ETest\Services\AuthorizationService;
use AdyenPayment\Classes\E2ETest\Services\CreateCheckoutSeedDataService;
use AdyenPayment\Classes\E2ETest\Services\CreateInitialSeedDataService;
use AdyenPayment\Classes\E2ETest\Services\CreateWebhooksSeedDataService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialTestModuleFrontController
 */
class AdyenOfficialTestModuleFrontController extends ModuleFrontController
{
    /**
     * AdyenOfficialTestModuleFrontController constructor.
     *
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Handles request by generating seed data for testing purposes
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws Exception
     */
    public function postProcess()
    {
        $payload = json_decode(Tools::file_get_contents('php://input'), true);

        if ($payload['getHmacSignature'] && $payload['data']) {
            $this->getHmacSignature($payload['data']);

            return;
        }

        if ($payload['merchantReference'] && $payload['eventCode']) {
            $this->verifyWebhookStatus($payload['merchantReference'], $payload['eventCode']);

            return;
        }

        $url = $payload['url'] ?? '';
        $testApiKey = $payload['testApiKey'] ?? '';
        $liveApiKey = $payload['liveApiKey'] ?? '';

        try {
            if ($url === '' || $testApiKey === '' || $liveApiKey === '') {
                throw new InvalidDataException('Url, test api key and live api key are required parameters.');
            }

            $this->verifyManagementAPI($testApiKey, $liveApiKey);
            $credentials = $this->getAuthorizationCredentials();
            $this->registerServices();
            $this->createInitialSeedData($url, $credentials);
            $this->registerProxies($credentials);
            $customerId = $this->createCheckoutSeedData($testApiKey);
            $createWebhookSeedDataService = new CreateWebhooksSeedDataService();
            $ordersMerchantReferenceAndAmount = $createWebhookSeedDataService->createWebhookSeedData($customerId);
            $webhookData = $createWebhookSeedDataService->getWebhookAuthorizationData();
            exit(json_encode(array_merge(
                $ordersMerchantReferenceAndAmount,
                $webhookData,
                ['message' => 'The initial data setup was successfully completed.']
            )));
        } catch (InvalidDataException $exception) {
            AdyenPrestaShopUtility::die400(
                [
                    'message' => $exception->getMessage(),
                ]
            );
        } catch (HttpRequestException $exception) {
            header('HTTP/1.1 503 Service Unavailable');
            exit(json_encode(['message' => $exception->getMessage()]));
        } catch (Exception $exception) {
            header('HTTP/1.1 500 Internal Server Error');
            exit(json_encode(['message' => $exception->getMessage()]));
        } finally {
            header('Content-Type: application/json');
        }
    }

    /**
     * Registers core services
     *
     * @return void
     */
    private function registerServices(): void
    {
        ServiceRegister::registerService(
            CreateIntegrationDataService::class,
            static function () {
                return new CreateIntegrationDataService('./modules/adyenofficial');
            }
        );
    }

    /**
     * Registers proxies for webservice rest api requests
     *
     * @param string $credentials
     *
     * @return void
     */
    private function registerProxies(string $credentials): void
    {
        $host = Configuration::get('PS_SHOP_DOMAIN');

        ServiceRegister::registerService(
            CountryTestProxy::class,
            static function () use ($credentials, $host) {
                return new CountryTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );

        ServiceRegister::registerService(
            CurrencyTestProxy::class,
            static function () use ($credentials, $host) {
                return new CurrencyTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );

        ServiceRegister::registerService(
            CustomerTestProxy::class,
            static function () use ($credentials, $host) {
                return new CustomerTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );

        ServiceRegister::registerService(
            AddressTestProxy::class,
            static function () use ($credentials, $host) {
                return new AddressTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );

        ServiceRegister::registerService(
            CartTestProxy::class,
            static function () use ($credentials, $host) {
                return new CartTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );

        ServiceRegister::registerService(
            ProductTestProxy::class,
            static function () use ($credentials, $host) {
                return new ProductTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );

        ServiceRegister::registerService(
            OrderTestProxy::class,
            static function () use ($credentials, $host) {
                return new OrderTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
            }
        );
    }

    /**
     * Returns hmac signature for given data
     *
     * @param array $data
     *
     * @return void
     *
     * @throws HttpRequestException
     * @throws Adyen\Webhook\Exception\InvalidDataException
     */
    private function getHmacSignature(array $data): void
    {
        ServiceRegister::registerService(
            CreateIntegrationDataService::class,
            static function () {
                return new CreateIntegrationDataService('./custom/plugins/AdyenPayment');
            }
        );
        $createWebhookDataService = new CreateWebhooksSeedDataService();
        $hmac = $createWebhookDataService->getWebhookAuthorizationData()['hmac'];
        $hmacSignature = new HmacSignature();
        unset($data['additionalData']);
        exit(json_encode(array_merge(
            ['hmacSignature' => $hmacSignature->calculateNotificationHMAC($hmac, $data)]
        )));
    }

    /**
     * Calls service to verify if OrderUpdate queue item is in terminal state
     *
     * @param string $merchantReference
     * @param string $eventCode
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     */
    private function verifyWebhookStatus(string $merchantReference, string $eventCode): void
    {
        $transactionLogService = new TransactionLogService();

        exit(json_encode(array_merge(
            ['finished' => $transactionLogService->findLogsByMerchantReference($merchantReference, $eventCode)]
        )));
    }

    /**
     * Calls service to verify if management api is stable
     *
     * @param string $testApiKey
     * @param string $liveApiKey
     *
     * @return void
     *
     * @throws HttpRequestException
     */
    private function verifyManagementApi(string $testApiKey, string $liveApiKey): void
    {
        (new AdyenAPIService())->verifyManagementAPI($testApiKey, $liveApiKey);
    }

    /**
     * Calls service to create authorization credentials for webservice rest api
     *
     * @return string
     *
     * @throws Exception
     */
    private function getAuthorizationCredentials(): string
    {
        return (new AuthorizationService())->getAuthorizationCredentials();
    }

    /**
     * Calls service to create initial seed data
     *
     * @throws QueryFilterInvalidParamException
     * @throws HttpRequestException
     */
    private function createInitialSeedData(string $url, string $credentials): void
    {
        (new CreateInitialSeedDataService($url, $credentials))->createInitialData();
    }

    /**
     * Calls service to create checkout seed data and returns existing customer id
     *
     * @param string $testApiKey
     *
     * @return string
     *
     * @throws ApiCredentialsDoNotExistException
     * @throws ApiKeyCompanyLevelException
     * @throws ClientKeyGenerationFailedException
     * @throws ConnectionSettingsNotFoundException
     * @throws EmptyConnectionDataException
     * @throws EmptyStoreException
     * @throws FailedToGenerateHmacException
     * @throws FailedToRegisterWebhookException
     * @throws HttpRequestException
     * @throws InvalidAllowedOriginException
     * @throws InvalidApiKeyException
     * @throws InvalidConnectionSettingsException
     * @throws InvalidModeException
     * @throws MerchantDoesNotExistException
     * @throws MerchantIdChangedException
     * @throws ModeChangedException
     * @throws PaymentMethodDataEmptyException
     * @throws PrestaShopException
     * @throws UserDoesNotHaveNecessaryRolesException
     */
    private function createCheckoutSeedData(string $testApiKey): string
    {
        return (new CreateCheckoutSeedDataService())->createCheckoutPrerequisitesData($testApiKey);
    }
}
