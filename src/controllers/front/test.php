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
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\E2ETest\Exception\InvalidDataException;
use AdyenPayment\Classes\E2ETest\Http\AddressTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CartTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CountryTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CurrencyTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CustomerTestProxy;
use AdyenPayment\Classes\E2ETest\Http\OrderTestProxy;
use AdyenPayment\Classes\E2ETest\Http\ProductTestProxy;
use AdyenPayment\Classes\E2ETest\Http\ShopsTestProxy;
use AdyenPayment\Classes\E2ETest\Services\AdyenAPIService;
use AdyenPayment\Classes\E2ETest\Services\AuthorizationService;
use AdyenPayment\Classes\E2ETest\Services\CreateCheckoutSeedDataService;
use AdyenPayment\Classes\E2ETest\Services\CreateInitialSeedDataService;
use AdyenPayment\Classes\E2ETest\Services\CreateWebhooksSeedDataService;
use AdyenPayment\Classes\E2ETest\Services\TransactionLogService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use PrestaShop\PrestaShop\Adapter\Entity\Country;
use Configuration;

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
     * @throws QueryFilterInvalidParamException
     * @throws Exception
     */
    public function postProcess()
    {
        $payload = json_decode(Tools::file_get_contents('php://input'), true);

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

            $this->verifyManagementApi($testApiKey, $liveApiKey);
            $credentials = $this->getAuthorizationCredentials();
            $host = Configuration::get('PS_SHOP_DOMAIN');
            $shopProxy = new ShopsTestProxy($this->getHttpClient(), $host, $credentials);
            $this->createInitialSeedData($url, $shopProxy);
            $host = Configuration::get('PS_SHOP_DOMAIN');
            $countryTestProxy = new CountryTestProxy($this->getHttpClient(), $host, $credentials);
            $currencyTestProxy = new CurrencyTestProxy($this->getHttpClient(), $host, $credentials);
            $customerTestProxy = new CustomerTestProxy($this->getHttpClient(), $host, $credentials);
            $addressTestProxy = new AddressTestProxy($this->getHttpClient(), $host, $credentials);
            $customerId = $this->createCheckoutSeedData(
                $countryTestProxy,
                $currencyTestProxy,
                $customerTestProxy,
                $addressTestProxy,
                $testApiKey
            );

            $cartTestProxy = new CartTestProxy($this->getHttpClient(), $host, $credentials);
            $productTestProxy = new ProductTestProxy($this->getHttpClient(), $host, $credentials);
            $orderTestProxy = new OrderTestProxy($this->getHttpClient(), $host, $credentials);
            $createWebhookSeedDataService = new CreateWebhooksSeedDataService(
                $cartTestProxy,
                $productTestProxy,
                $orderTestProxy
            );
            $ordersMerchantReferenceAndAmount = $createWebhookSeedDataService->createWebhookSeedData($customerId);
            $webhookData = $createWebhookSeedDataService->getWebhookAuthorizationData();
            die(json_encode(array_merge(
                $ordersMerchantReferenceAndAmount,
                $webhookData,
                ['message' => 'The initial data setup was successfully completed.']
            )));
        } catch (InvalidDataException $exception) {
            AdyenPrestaShopUtility::die400(
                [
                    'message' => $exception->getMessage()
                ]
            );
        } catch (HttpRequestException $exception) {
            header('HTTP/1.1 503 Service Unavailable');
            die(json_encode(['message' => $exception->getMessage()]));
        } catch (Exception $exception) {
            header('HTTP/1.1 500 Internal Server Error');
            die(json_encode(['message' => $exception->getMessage()]));
        } finally {
            header('Content-Type: application/json');
        }
    }

    /**
     * @throws QueryFilterInvalidParamException
     */
    private function verifyWebhookStatus($merchantReference, $eventCode): void
    {
        $transactionLogService = new TransactionLogService();

        die(json_encode(array_merge(
            ['finished' => $transactionLogService->findLogsByMerchantReference($merchantReference, $eventCode)]
        )));
    }

    /**
     * Calls service to verify if management api is stable
     *
     * @param string $testApiKey
     * @param string $liveApiKey
     * @return void
     * @throws HttpRequestException
     */
    private function verifyManagementApi(string $testApiKey, string $liveApiKey): void
    {
        $adyenApiService = new AdyenAPIService();
        $adyenApiService->verifyManagementAPI($testApiKey, $liveApiKey);
    }

    /**
     * Calls service to create authorization credentials for webservice rest api
     *
     * @return string
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
    private function createInitialSeedData(string $url, ShopsTestProxy $shopsTestProxy): void
    {
        $createSeedDataService = new CreateInitialSeedDataService($url, $shopsTestProxy);
        $createSeedDataService->createInitialData();
    }

    /**
     * Calls service to create checkout seed data
     *
     * @param CountryTestProxy $countryTestProxy
     * @param CurrencyTestProxy $currencyTestProxy
     * @param CustomerTestProxy $customerTestProxy
     * @param AddressTestProxy $addressTestProxy
     * @param string $testApiKey
     * @return string
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
     * @throws UserDoesNotHaveNecessaryRolesException
     */
    private function createCheckoutSeedData(
        CountryTestProxy  $countryTestProxy,
        CurrencyTestProxy $currencyTestProxy,
        CustomerTestProxy $customerTestProxy,
        AddressTestProxy  $addressTestProxy,
        string            $testApiKey
    ): string
    {
        $createSeedDataService = new CreateCheckoutSeedDataService(
            $countryTestProxy,
            $currencyTestProxy,
            $customerTestProxy,
            $addressTestProxy
        );

        return $createSeedDataService->crateCheckoutPrerequisitesData($testApiKey);
    }

    /**
     * @return HttpClient
     */
    private function getHttpClient(): HttpClient
    {
        return ServiceRegister::getService(HttpClient::class);
    }
}
