<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\BusinessLogic\E2ETest\Services\CreateIntegrationDataService;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\E2ETest\Http\ShopsTestProxy;
use AdyenPayment\Classes\E2ETest\Http\TestProxy;
use Configuration;
use Module;
use Shop;

/**
 * Class CreateInitialSeedDataService
 *
 * @package AdyenPayment\Classes\E2ETest\Services
 */
class CreateInitialSeedDataService extends BaseCreateSeedDataService
{
    /**
     * @var TestProxy
     */
    private $shopProxy;
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * CreateSeedDataService constructor.
     *
     * @param string $url
     * @param string $credentials
     */
    public function __construct(string $url, string $credentials)
    {
        $host = Configuration::get('PS_SHOP_DOMAIN');
        $this->shopProxy = new ShopsTestProxy(ServiceRegister::getService(HttpClient::class), $host, $credentials);
        $this->baseUrl = $url;
    }

    /**
     * @throws QueryFilterInvalidParamException
     * @throws HttpRequestException
     * @throws \PrestaShopException
     */
    public function createInitialData(): void
    {
        Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
        $this->createSubStores();
        $this->updateBaseUrlAndDefaultShopName();
        $this->getCreateIntegrationDataService()->saveTestHostname($this->baseUrl);
    }

    /**
     * Creates new subStores using xml file data
     *
     * @throws HttpRequestException|\PrestaShopException
     */
    public function createSubStores(): void
    {
        $shops = $this->shopProxy->getSubStores();
        $shopUrls = $this->shopProxy->getSubStoreUrls();
        $newSubStores = $this->readFromJSONFile()['newSubStores'] ?? [];

        if (array_key_exists('shops', $shops) && count($shops['shops']) === 1) {
            foreach ($newSubStores as $newSubStore) {
                $data = $this->readFomXMLFile('create_shop');
                $data = str_replace('{name}', $newSubStore['subStore'], $data);
                $this->shopProxy->createSubStore(['data' => $data]);
            }
        }

        if (array_key_exists('shop_urls', $shopUrls) && count($shopUrls['shop_urls']) === 1) {
            foreach ($newSubStores as $newSubStore) {
                $data = $this->readFomXMLFile('create_shop_url');
                $data = str_replace(
                    [
                        '{id_shop}',
                        '{host}',
                        '{virtual_uri}'
                    ],
                    [
                        $newSubStore['id'],
                        parse_url($this->baseUrl)['host'],
                        $newSubStore['subStore'] . '/'
                    ],
                    $data
                );
                $this->shopProxy->createShopUrl(['data' => $data]);
            }
        }

        $this->enableModuleInNewSubStore(2);
        $this->enableModuleInNewSubStore(3);
    }

    /**
     * Updates baseUrl in database and default shop name
     *
     * @throws HttpRequestException
     */
    public function updateBaseUrlAndDefaultShopName(): void
    {
        $data = $this->readFomXMLFile('update_shop');
        $this->shopProxy->updateSubStore(['data' => $data]);
        $data = $this->readFomXMLFile('update_shop_url');
        $host = parse_url($this->baseUrl)['host'];
        $data = str_replace('{host}', $host, $data);
        $this->shopProxy->updateSubStoreUrl(['data' => $data]);
        Configuration::updateValue('PS_SHOP_DOMAIN', $host);
        Configuration::updateValue('PS_SHOP_DOMAIN_SSL', $host);
    }

    /**
     * Enables module in given subStore
     *
     * @param int $subStoreId
     * @return void
     * @throws \PrestaShopException
     */
    private function enableModuleInNewSubStore(int $subStoreId): void
    {
        Shop::setContext(1, $subStoreId);
        $module = Module::getInstanceByName('adyenofficial');
        $module->enable();
    }

    /**
     * Returns CreateIntegrationDataService instance
     *
     * @return CreateIntegrationDataService
     */
    private function getCreateIntegrationDataService(): CreateIntegrationDataService
    {
        return ServiceRegister::getService(CreateIntegrationDataService::class);
    }
}