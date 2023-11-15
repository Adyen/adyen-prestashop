<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Core\BusinessLogic\E2ETest\Services\CreateSeedDataService as BaseCreateSeedDataService;
use AdyenPayment\Classes\E2ETest\Http\ShopsTestProxy;
use AdyenPayment\Classes\E2ETest\Http\TestProxy;
use Configuration;
use Module;
use Shop;

/**
 * Class CreateSeedDataService
 *
 * @package AdyenPayment\E2ETest\Services
 */
class CreateSeedDataService extends BaseCreateSeedDataService
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
        $this->shopProxy = new ShopsTestProxy($this->getHttpClient(), 'localhost', $credentials);
        $this->baseUrl = $url;
    }

    /**
     * @throws QueryFilterInvalidParamException
     * @throws HttpRequestException
     */
    public function createInitialData(): void
    {
        Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
        $this->createSubStores();
        $this->updateBaseUrlAndDefaultShopName();
    }

    /**
     * Updates baseUrl in database and default shop name
     *
     * @throws HttpRequestException
     */
    public function updateBaseUrlAndDefaultShopName(): void
    {
        $data = $this->readFomXMLFile('update_shop');
        $this->shopProxy->updateDefaultShopName(['data' => $data]);
        $data = $this->readFomXMLFile('update_shop_url');
        $data = str_replace('{host}', parse_url($this->baseUrl)['host'], $data);
        $this->shopProxy->updateShopUrl(['data' => $data]);
    }

    /**
     * Creates new subStores using xml file data
     *
     * @throws HttpRequestException
     */
    public function createSubStores(): void
    {
        $shops = $this->shopProxy->getSubStores();
        if (array_key_exists('shops', $shops) && count($shops['shops']) === 1) {
            $data = $this->readFomXMLFile('create_shop');
            $this->shopProxy->createSubStore(['data' => $data]);
        }

        $shopUrls = $this->shopProxy->getSubStoreUrls();
        if (array_key_exists('shop_urls', $shopUrls) && count($shopUrls['shop_urls']) === 1) {
            $data = $this->readFomXMLFile('create_shop_url');
            $data = str_replace('{host}', parse_url($this->baseUrl)['host'], $data);
            $this->shopProxy->createShopUrl(['data' => $data]);
        }

        $this->enableModuleInNewSubStore();
    }

    /**
     * Enables module in second substore
     *
     * @return void
     */
    private function enableModuleInNewSubStore(): void
    {
        Shop::setContext(1, 2);
        $module = Module::getInstanceByName('adyenofficial');
        $module->enable();
    }

    /**
     * @param string $fileName
     * @return string
     */
    private function readFomXMLFile(string $fileName): string
    {
        return file_get_contents(
            './modules/adyenofficial/classes/E2ETest/Data/' . $fileName . '.xml',
            FILE_USE_INCLUDE_PATH
        );
    }

    /**
     * @return HttpClient
     */
    private function getHttpClient(): HttpClient
    {
        return ServiceRegister::getService(HttpClient::class);
    }
}