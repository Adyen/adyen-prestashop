<?php

use Adyen\Core\Infrastructure\Configuration\ConfigurationManager;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\E2ETest\Exception\InvalidDataException;
use AdyenPayment\Classes\E2ETest\Services\AdyenAPIService;
use AdyenPayment\Classes\E2ETest\Services\AuthorizationService;
use AdyenPayment\Classes\E2ETest\Services\CreateCheckoutSeedDataService;
use AdyenPayment\Classes\E2ETest\Services\CreateInitialSeedDataService;
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
     * @throws QueryFilterInvalidParamException
     * @throws Exception
     */
    public function postProcess(): void
    {
        $payload = json_decode(Tools::file_get_contents('php://input'), true);

        if ($payload['checkoutSeedDataRequest']) {
            $this->handleCheckoutSeedDataRequest($payload);

            return;
        }

        $this->handleInitialSeedDataRequest($payload);
    }

    private function handleInitialSeedDataRequest(array $payload): void
    {
        $url = $payload['url'] ?? '';
        $testApiKey = $payload['testApiKey'] ?? '';
        $liveApiKey = $payload['liveApiKey'] ?? '';

        try {
            if ($url === '' || $testApiKey === '' || $liveApiKey === '') {
                throw new InvalidDataException('Url, test api key and live api key are required parameters.');
            }

            $adyenApiService = new AdyenAPIService();
            $adyenApiService->verifyManagementAPI($testApiKey, $liveApiKey);
            $authorizationService = new AuthorizationService();
            $credentials = $authorizationService->getAuthorizationCredentials();
            $createSeedDataService = new CreateInitialSeedDataService($url, $credentials);
            $createSeedDataService->createInitialData();
            die(json_encode(['message' => 'The initial data setup was successfully completed.']));
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
    private function handleCheckoutSeedDataRequest(array $payload): void
    {
        $checkoutSeedData = $this->getConfigurationManager()->getConfigValue('checkoutSeedData');
        if ($checkoutSeedData) {
            return;
        }

        $this->getConfigurationManager()->saveConfigValue('checkoutSeedData', true);
        $testApiKey = $payload['testApiKey'] ?? '';

        try {
            if ($testApiKey === '') {
                throw new InvalidDataException('Test api key is required parameter.');
            }

            $authorizationService = new AuthorizationService();
            $credentials = $authorizationService->getAuthorizationCredentials();
            $createSeedDataService = new CreateCheckoutSeedDataService($credentials);
            $createSeedDataService->crateCheckoutPrerequisitesData($testApiKey);
            die(json_encode(['message' => 'The checkout data setup was successfully completed.']));
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
     * @return ConfigurationManager
     */
    private function getConfigurationManager(): ConfigurationManager
    {
        return ServiceRegister::getService(ConfigurationManager::CLASS_NAME);
    }
}
