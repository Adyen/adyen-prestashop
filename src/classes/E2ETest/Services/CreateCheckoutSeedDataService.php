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
use AdyenPayment\Classes\E2ETest\Http\AddressTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CountryTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CurrencyTestProxy;
use AdyenPayment\Classes\E2ETest\Http\CustomerTestProxy;
use Configuration;
use Module;
use PaymentModule;
use PrestaShop\PrestaShop\Adapter\Entity\Currency;
use PrestaShop\PrestaShop\Adapter\Entity\Country;
use PrestaShop\PrestaShop\Adapter\Entity\Carrier;
use PrestaShop\PrestaShop\Adapter\Entity\Product;
use Shop;

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
     * @var CurrencyTestProxy
     */
    private $currencyTestProxy;
    /**
     * @var CustomerTestProxy
     */
    private $customerTestProxy;
    /**
     * @var AddressTestProxy
     */
    private $addressTestProxy;

    /**
     * CreateCheckoutSeedDataService constructor
     *
     * @param CountryTestProxy $countryTestProxy
     * @param CurrencyTestProxy $currencyTestProxy
     * @param CustomerTestProxy $customerTestProxy
     * @param AddressTestProxy $addressTestProxy
     */
    public function __construct(
        CountryTestProxy  $countryTestProxy,
        CurrencyTestProxy $currencyTestProxy,
        CustomerTestProxy $customerTestProxy,
        AddressTestProxy  $addressTestProxy
    )
    {
        $this->countryTestProxy = $countryTestProxy;
        $this->currencyTestProxy = $currencyTestProxy;
        $this->customerTestProxy = $customerTestProxy;
        $this->addressTestProxy = $addressTestProxy;
    }

    /**
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
     * @throws \PrestaShopException
     */
    public function crateCheckoutPrerequisitesData(string $testApiKey): string
    {
        $this->createIntegrationConfigurations($testApiKey);
        $this->activateCountries();
        $this->deactivateCountries();
        $this->addCurrencies();
        $this->updateProductPrice();
        return $this->createCustomerAndAddress();
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

            $data = $this->readFomXMLFile('update_country');
            $data = str_replace(
                [
                    '{id}',
                    '{id_zone}',
                    '{iso_code}',
                    '{active}',
                    '{contains_states}',
                    '{need_identification_number}',
                    '{display_tax_label}',
                    '{language1}'
                ],
                [
                    $countryId,
                    $countryData['id_zone'],
                    $countriesIsoCode,
                    1,
                    $countryData['contains_states'] ?? 0,
                    $countryData['need_identification_number'] ?? 0,
                    $countryData['display_tax_label'],
                    $countryData['name']
                ],
                $data
            );

            $this->countryTestProxy->updateCountry($countryId, ['data' => $data]);
            $this->enableCarrierInSpecificZone($countryId);
        }

        $moduleId = Module::getInstanceByName('adyenofficial')->id;
        $countries = Country::getCountriesByIdShop(1, 1);
        Country::addModuleRestrictions([], $countries, [['id_module' => $moduleId]]);
    }

    /**
     * @throws HttpRequestException
     */
    private function deactivateCountries(): void
    {
        $countriesIsoCodes = array_column($this->readFromJSONFile()['deactivateCountries'] ?? [], 'iso');
        foreach ($countriesIsoCodes as $countriesIsoCode) {
            $countryId = Country::getByIso($countriesIsoCode);
            $countryData = $this->countryTestProxy->getCountryData($countryId)['country'];

            if (!$countryData) {
                return;
            }

            $data = $this->readFomXMLFile('update_country');
            $data = str_replace(
                [
                    '{id}',
                    '{id_zone}',
                    '{iso_code}',
                    '{active}',
                    '{contains_states}',
                    '{need_identification_number}',
                    '{display_tax_label}',
                    '{language1}'
                ],
                [
                    $countryId,
                    $countryData['id_zone'],
                    $countriesIsoCode,
                    0,
                    $countryData['contains_states'] ?? 0,
                    $countryData['need_identification_number'] ?? 0,
                    $countryData['display_tax_label'] ?? 0,
                    $countryData['name']
                ],
                $data
            );

            $this->countryTestProxy->updateCountry($countryId, ['data' => $data]);
        }
    }

    /**
     * Activates currencies if already exist and create new currencies if they don't
     *
     * @throws HttpRequestException
     */
    private function addCurrencies(): void
    {
        $currencies = $this->readFromJSONFile()['currencies'] ?? [];
        foreach ($currencies as $currency) {
            if (Currency::exists($currency['isoCode'])) {
                $this->updateExistingCurrency($currency['isoCode']);

                continue;
            }

            $this->createCurrency($currency);
        }
    }

    /**
     * Activates existing currency
     *
     * @throws HttpRequestException
     */
    private function updateExistingCurrency(string $isoCode): void
    {
        $currencyId = Currency::getIdByIsoCode($isoCode);
        $currencyData = $this->currencyTestProxy->getCurrencyData($currencyId)['currency'];
        if (!$currencyData || $currencyData['active'] === '1') {
            return;
        }

        $data = $this->readFomXMLFile('update_currency');
        $data = str_replace(
            [
                '{id}',
                '{name}',
                '{iso_code}',
                '{precision}',
                '{conversion_rate}',
                '{active}',
                '{language1}',
                '{symbol_language1}'
            ],
            [
                $currencyId,
                $currencyData['name'],
                $isoCode,
                $currencyData['precision'],
                $currencyData['conversion_rate'],
                1,
                $currencyData['names'],
                $currencyData['symbol']
            ],
            $data
        );

        $this->currencyTestProxy->updateCurrency($currencyId, ['data' => $data]);
    }

    /**
     * Creates new currency
     *
     * @throws HttpRequestException
     */
    private function createCurrency(array $currencyTestData): void
    {
        $data = $this->readFomXMLFile('create_currency');
        $data = str_replace(
            [
                '{name}',
                '{language1}',
                '{symbol_language1}',
                '{iso_code}',
                '{precision}',
                '{conversion_rate}',
                '{active}',
            ],
            [
                $currencyTestData['name'],
                $currencyTestData['name'],
                $currencyTestData['symbol'],
                $currencyTestData['isoCode'],
                $currencyTestData['precision'],
                $currencyTestData['conversionRate'],
                1
            ],
            $data
        );

        $createdCurrency = $this->currencyTestProxy->createCurrency(['data' => $data])['currency'];
        if ($createdCurrency) {
            $createdCurrencyId = (int)$createdCurrency['id'];
            $moduleId = Module::getInstanceByName('adyenofficial')->id;
            PaymentModule::addCurrencyPermissions($createdCurrencyId, [$moduleId]);
        }
    }

    private function enableCarrierInSpecificZone(string $countryId): void
    {
        $zoneId = Country::getIdZone($countryId);
        $carrier = new Carrier(1);
        $zones = $carrier->getZone($zoneId);
        if (empty($zones)) {
            $carrier->addZone($zoneId);
        }
    }

    /**
     * Creates customer and address in database
     *
     * @return string
     * @throws HttpRequestException
     */
    private function createCustomerAndAddress(): string
    {
        $customer = $this->readFromJSONFile()['customer'] ?? [];
        $createdCustomerId = $this->createCustomer($customer);
        $this->createCustomerAddress($customer, $createdCustomerId);

        return $createdCustomerId;
    }

    /**
     * Creates customer
     *
     * @param array $customer
     * @return string
     * @throws HttpRequestException
     */
    private function createCustomer(array $customer): string
    {
        $data = $this->readFomXMLFile('create_customer');
        $data = str_replace(
            [
                '{id_default_group}',
                '{id_lang}',
                '{passwd}',
                '{lastname}',
                '{firstname}',
                '{email}',
                '{id_gender}',
                '{birthday}',
                '{active}',
                '{is_guest}',
                '{id_shop}',
                '{id_shop_group}'
            ],
            [
                $customer['defaultGroupId'],
                $customer['langId'],
                $customer['password'],
                $customer['lastName'],
                $customer['firstName'],
                $customer['email'],
                $customer['genderId'],
                $customer['birthday'],
                1,
                0,
                1,
                1
            ],
            $data
        );

        $createdCustomer = $this->customerTestProxy->createCustomer(['data' => $data])['customer'];
        if ($createdCustomer) {
            return $createdCustomer['id'];
        }

        return '';
    }

    /**
     * Creates customer's address
     *
     * @param array $customer
     * @param string $createdCustomerId
     * @return void
     * @throws HttpRequestException
     */
    private function createCustomerAddress(array $customer, string $createdCustomerId): void
    {
        $addressData = $customer['address'];
        $data = $this->readFomXMLFile('create_address');
        $data = str_replace(
            [
                '{id_customer}',
                '{id_country}',
                '{alias}',
                '{lastname}',
                '{firstname}',
                '{address1}',
                '{postcode}',
                '{city}'
            ],
            [
                $createdCustomerId,
                Country::getByIso($addressData['country']),
                $addressData['alias'],
                $customer['lastName'],
                $customer['firstName'],
                $addressData['address'],
                $addressData['postalCode'],
                $addressData['city'],
            ],
            $data
        );

        $this->addressTestProxy->createAddress(['data' => $data]);
    }

    /**
     * @throws \PrestaShopException
     */
    private function updateProductPrice(): void
    {
        $product = new Product(3);
        $product->price = 30.000000;
        $result = $product->save();

        echo $result;
    }

    /**
     * @return HttpClient
     */
    private function getHttpClient(): HttpClient
    {
        return ServiceRegister::getService(HttpClient::class);
    }
}