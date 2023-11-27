<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
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
use Adyen\Core\BusinessLogic\E2ETest\Services\CreateIntegrationDataService;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\E2ETest\Http\CountryTestProxy;
use PrestaShop\PrestaShop\Adapter\Entity\Country;

/**
 * Class CreateCheckoutSeedDataService
 *
 * @package AdyenPayment\E2ETest\Services
 */
class CreateCheckoutSeedDataService extends BaseCreateSeedDataService
{
    /**
     * @var CountryTestProxy
     */
    private $countryTestProxy;
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * CreateCheckoutSeedDataService constructor
     *
     * @param string $credentials
     */
    public function __construct(string $credentials)
    {
        $this->countryTestProxy = new CountryTestProxy($this->getHttpClient(), 'localhost', $credentials);
    }

    /**
     * @throws EmptyConnectionDataException
     * @throws ApiKeyCompanyLevelException
     * @throws MerchantDoesNotExistException
     * @throws InvalidModeException
     * @throws EmptyStoreException
     * @throws MerchantIdChangedException
     * @throws InvalidApiKeyException
     * @throws PaymentMethodDataEmptyException
     * @throws FailedToGenerateHmacException
     * @throws ClientKeyGenerationFailedException
     * @throws UserDoesNotHaveNecessaryRolesException
     * @throws ApiCredentialsDoNotExistException
     * @throws InvalidAllowedOriginException
     * @throws InvalidConnectionSettingsException
     * @throws ModeChangedException
     * @throws ConnectionSettingsNotFoundException
     * @throws FailedToRegisterWebhookException
     * @throws HttpRequestException
     */
    public function crateCheckoutPrerequisitesData(string $testApiKey): void
    {
        if (count(AdminAPI::get()->connection(1)->getConnectionSettings()->toArray()) > 0) {
            return;
        }

        $this->createIntegrationConfigurations($testApiKey);
        $this->activateCountries();
    }

    /**
     * @throws HttpRequestException
     */
    private function activateCountries(): void
    {
        $countriesIsoCodes = array_column($this->readFromJSONFile()['countries'] ?? [], 'iso');
        foreach ($countriesIsoCodes as $countriesIsoCode) {
            $countryId = Country::getByIso($countriesIsoCode);
            $countryData = $this->countryTestProxy->getCountryData($countryId)['country'];

            if (!$countryData) {
                return;
            }

            $data = $this->readFomXMLFile('activate_country');
            $data = str_replace(
                [
                    '{id}',
                    '{id_zone}',
                    '{iso_code}',
                    '{active}',
                    '{contains_states}',
                    '{need_identification_number}',
                    '{display_tax_label}'
                ],
                [
                    $countryId,
                    $countryData['id_zone'],
                    $countriesIsoCode,
                    1,
                    $countryData['contains_states'],
                    $countryData['need_identification_number'],
                    $countryData['display_tax_label']
                ],
                $data
            );
            $countryNames = $countryData['name'];
            foreach ($countryNames as $countryName) {
                $id = $countryName['id'];
                $data = str_replace("{language$id}", $countryName['value'], $data);
            }

            $this->countryTestProxy->updateCountry($countryId, ['data' => $data]);
        }
    }

    /**
     * Creates the integration configuration - authorization data and payment methods
     *
     * @throws EmptyConnectionDataException
     * @throws ApiKeyCompanyLevelException
     * @throws MerchantDoesNotExistException
     * @throws InvalidModeException
     * @throws EmptyStoreException
     * @throws InvalidApiKeyException
     * @throws MerchantIdChangedException
     * @throws ClientKeyGenerationFailedException
     * @throws FailedToGenerateHmacException
     * @throws UserDoesNotHaveNecessaryRolesException
     * @throws InvalidAllowedOriginException
     * @throws ApiCredentialsDoNotExistException
     * @throws InvalidConnectionSettingsException
     * @throws ModeChangedException
     * @throws ConnectionSettingsNotFoundException
     * @throws FailedToRegisterWebhookException
     * @throws PaymentMethodDataEmptyException
     */
    private function createIntegrationConfigurations(string $testApiKey): void
    {
        $createIntegrationDataService = new CreateIntegrationDataService('./modules/adyenofficial');
        $createIntegrationDataService->createConnectionAndWebhookConfiguration($testApiKey);
        $createIntegrationDataService->createAllPaymentMethodsFromTestData();
    }

    /**
     * @return HttpClient
     */
    private function getHttpClient(): HttpClient
    {
        return ServiceRegister::getService(HttpClient::class);
    }
}