<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\DataAccess\Connection\Entities\ConnectionSettings;
use Adyen\Core\BusinessLogic\Domain\Integration\Store\StoreService as StoreServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Stores\Exceptions\InvalidShopOrderDataException;
use Adyen\Core\BusinessLogic\Domain\Stores\Models\Store;
use Adyen\Core\BusinessLogic\Domain\Stores\Models\StoreOrderStatus;
use Adyen\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use AdyenPayment\Classes\Repositories\ConfigurationRepository;
use AdyenPayment\Classes\Services\AdyenOrderStatusMapping;
use Module;
use PrestaShop\PrestaShop\Adapter\Entity\OrderState;
use PrestaShop\PrestaShop\Adapter\Entity\Shop;

/**
 * Class StoreService
 *
 * @package AdyenPayment\Integration
 */
class StoreService implements StoreServiceInterface
{
    /**
     * @var ConfigurationRepository
     */
    private $configurationRepository;

    /**
     * @var RepositoryInterface
     */
    private $connectionRepository;

    /**
     * @param ConfigurationRepository $configurationRepository
     * @param RepositoryInterface $connectionRepository
     */
    public function __construct(
        ConfigurationRepository $configurationRepository,
        RepositoryInterface     $connectionRepository
    )
    {
        $this->configurationRepository = $configurationRepository;
        $this->connectionRepository = $connectionRepository;
    }

    /**
     * @inheritDoc
     */
    public function getStoreDomain(): string
    {
        $storeId = StoreContext::getInstance()->getStoreId();
        $shop = \Shop::getShop($storeId);

        if (strpos($shop['domain'], '/') === false) {
            return \Tools::getShopProtocol() . $shop['domain'];
        }

        return \Tools::getShopProtocol() . substr($shop['domain'], 0, strpos($shop['domain'], '/'));
    }

    /**
     * @inheritDoc
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getStores(): array
    {
        $stores = [];

        foreach (Shop::getShops() as $shop) {
            $stores[] = new Store($shop['id_shop'], $shop['name'], $this->isStoreInMaintenanceMode($shop['id_shop']));
        }

        return $stores;
    }

    /**
     * @inheritDoc
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getDefaultStore(): ?Store
    {
        $defaultStore = null;
        $defaultStoreId = \Configuration::get('PS_SHOP_DEFAULT');

        foreach (Shop::getShops() as $shop) {
            if ($shop['id_shop'] === $defaultStoreId) {
                $defaultStore = new Store(
                    $shop['id_shop'],
                    $shop['name'],
                    $this->isStoreInMaintenanceMode($shop['id_shop'])
                );
                break;
            }
        }

        return $defaultStore;
    }

    /**
     * @inheritDoc
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getStoreById(string $id): ?Store
    {
        $shop = Shop::getShop($id);

        if (!$shop) {
            return null;
        }

        return new Store($shop['id_shop'], $shop['name'], $this->isStoreInMaintenanceMode($shop['id_shop']));
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidShopOrderDataException
     */
    public function getStoreOrderStatuses(): array
    {
        return $this->transformStoreOrderStatuses(OrderState::getOrderStates(\Context::getContext()->language->id));
    }

    /**
     * @inheritDoc
     */
    public function getDefaultOrderStatusMapping(): array
    {
        return AdyenOrderStatusMapping::getDefaultOrderStatusMap();
    }

    /**
     * Retrieves connected stores ids.
     *
     * @return array
     */
    public function getConnectedStores(): array
    {
        /** @var ConnectionSettings[] $settings */
        $settings = $this->connectionRepository->select();
        $result = [];

        foreach ($settings as $item) {
            $result[] = $item->getStoreId();
        }

        return $result;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function checkStoreConnection(string $id): bool
    {
        $settings = $this->connectionRepository->select();

        foreach ($settings as $item) {
            if ($item->getStoreId() === $id) {
                $connectionSettings = $item->getConnectionSettings();
                $liveData = $connectionSettings->getLiveData();
                $testData = $connectionSettings->getTestData();

                if ($testData && $testData->getMerchantId() !== '') {
                    return true;
                }

                if ($liveData && $liveData->getMerchantId() !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws \PrestaShopDatabaseException
     */
    private function isStoreInMaintenanceMode(int $storeId): bool
    {
        return $this->configurationRepository->isStoreInMaintenanceMode($storeId, count(Shop::getShops()));
    }

    /**
     * @param array $orderStates
     *
     * @return array
     *
     * @throws InvalidShopOrderDataException
     */
    private function transformStoreOrderStatuses(array $orderStates): array
    {
        return array_map(function ($orderState) {
            if ($orderState['name'] === AdyenOrderStatusMapping::PRESTA_CHARGEBACK) {
                $orderState['name'] = Module::getInstanceByName('adyenofficial')->l('Chargeback');
            }
            if ($orderState['name'] === AdyenOrderStatusMapping::PRESTA_PENDING) {
                $orderState['name'] = Module::getInstanceByName('adyenofficial')->l('Pending');
            }
            if ($orderState['name'] === AdyenOrderStatusMapping::PRESTA_PARTIALLY_REFUNDED) {
                $orderState['name'] = Module::getInstanceByName('adyenofficial')->l('Partially refunded');
            }

            return new StoreOrderStatus(
                $orderState['id_order_state'],
                $orderState['name']
            );
        }, $orderStates);
    }
}
