<?php

namespace AdyenPayment\Classes\Utility;

use Adyen\Core\BusinessLogic\Domain\Disconnect\Services\DisconnectService;
use Adyen\Core\BusinessLogic\Domain\Integration\Store\StoreService as StoreServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Payment\Repositories\PaymentMethodConfigRepository;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenOfficial;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\ImageHandler;
use AdyenPayment\Classes\Services\Integration\StoreService;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Configuration;
use Db;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\OrderState;
use PrestaShopDatabaseException;
use PrestaShopException;
use Tab;

/**
 * Class Installer
 *
 * @package AdyenPayment\Utility
 */
class Installer
{
    /** @var string */
    private const ADYEN_ENTITY = 'adyen_entity';
    /** @var string */
    private const ADYEN_NOTIFICATIONS = 'adyen_notifications';
    /** @var string */
    private const ADYEN_TRANSACTION_LOG = 'adyen_transaction_log';
    /** @var string */
    private const ADYEN_QUEUE = 'adyen_queue';
    /** @var string */
    private const PENDING_STATE = 'Pending';
    /** @var string */
    private const PARTIALLY_REFUNDED_STATE = 'Partially refunded';
    /** @var string */
    private const CHARGEBACK_STATE = 'Chargeback';
    /** @var string */
    private const WAITING_FOR_PAYMENT_STATE = 'Waiting for payment';
    /** @var string */
    private const PAYMENT_NEEDS_ATTENTION_STATE = 'Payment needs attention';

    /** @var string[] */
    private static $controllers = [
        'AdyenAuthorization',
        'AdyenAutoTest',
        'AdyenDebug',
        'AdyenDisconnect',
        'AdyenGeneralSettings',
        'AdyenGivingSettings',
        'AdyenMerchant',
        'AdyenNotifications',
        'AdyenOrderStatuses',
        'AdyenOrderStatusMap',
        'AdyenPayment',
        'AdyenShopInformation',
        'AdyenState',
        'AdyenSystemInfo',
        'AdyenValidateConnection',
        'AdyenVersion',
        'AdyenWebhookNotifications',
        'AdyenWebhookValidation',
        'AdyenCapture',
        'AdyenPaymentLink'
    ];

    /** @var string[] */
    private static $hooks = [
        'actionFrontControllerSetMedia',
        'paymentOptions',
        'actionOrderStatusUpdate',
        'actionAdminControllerSetMedia',
        'displayCustomerAccount',
        'actionOrderSlipAdd',
        'moduleRoutes',
        'displayExpressCheckout',
        'displayBackOfficeHeader',
        'actionValidateOrder',
        'sendMailAlterTemplateVars',
        'displayOrderConfirmation',
        'displayPaymentReturn'
    ];

    /** @var string[] */
    private static $deprecated_hooks = [
        'paymentReturn'
    ];

    /** @var array */
    private static $allPrestaShopStatuses = [];

    /**
     * @var AdyenOfficial
     */
    private $module;

    /**
     * Installer class constructor.
     *
     * @param AdyenOfficial $module
     */
    public function __construct(AdyenOfficial $module)
    {
        $this->module = $module;
    }

    /**
     * Initializes plugin.
     * Creates database tables, adds admin controllers, hooks, order states and initializes configuration values.
     *
     * @return void
     *
     * @throws RepositoryClassException
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    public function install(): void
    {
        Bootstrap::init();

        $this->createTables();
        $this->addControllers();
        $this->addHooks();
        $this->activateCustomOrderStates();
    }

    /**
     * Drop database tables, remove hooks, controller, order states and configuration values
     *
     * @return void
     *
     * @throws RepositoryClassException
     *
     * @throws Exception
     */
    public function uninstall(): void
    {
        Bootstrap::init();
        $this->removeImages();
        ImageHandler::removeAdyenDirectory();
        $this->disconnect();
        $this->dropTables();
        $this->removeControllers();
        $this->deactivateCustomOrderStates();
    }

    /**
     * @return bool
     */
    public function shouldInstallOverrides(): bool
    {
        return $this->canAdyenOverride(
            _PS_ROOT_DIR_ . '/override/controllers/admin/AdminOrdersController.php'
        );
    }

    /**
     * Removes Admin controllers.
     *
     * @return void Controller deletion status
     *
     * @throws PrestaShopException
     * @throws Exception
     */
    public function removeControllers(): void
    {
        /** @var Tab[] $tabs */
        $tabs = Tab::getCollectionFromModule($this->module->name);
        if ($tabs && count($tabs)) {
            foreach ($tabs as $tab) {
                $success = $tab->delete();

                if (!$success) {
                    throw new Exception('Adyen plugin failed to remove controller: ' . $tab->name);
                }
            }
        }
    }

    /**
     * Adds controllers and hooks.
     *
     * @return void
     *
     * @throws Exception
     */
    public function addControllersAndHooks(): void
    {
        $this->addHooks();
        $this->addControllers();
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function deactivateCustomOrderStates(): void
    {
        $this->deactivateCustomOrderState(self::PENDING_STATE);
        $this->deactivateCustomOrderState(self::PARTIALLY_REFUNDED_STATE);
        $this->deactivateCustomOrderState(self::CHARGEBACK_STATE);
    }

    /**
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function deactivateOldCustomOrderStates(): void
    {
        $this->deactivateCustomOrderState(self::WAITING_FOR_PAYMENT_STATE);
        $this->deactivateCustomOrderState(self::PAYMENT_NEEDS_ATTENTION_STATE);
    }

    /**
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function activateCustomOrderStates(): void
    {
        $this->addCustomOrderState(self::PENDING_STATE, '#4169E1');
        $this->addCustomOrderState(self::PARTIALLY_REFUNDED_STATE, '#6F8C9F');
        $this->addCustomOrderState(self::CHARGEBACK_STATE, '#E74C3C');
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    private function disconnect()
    {
        try {
            /** @var DisconnectService $disconnectService */
            $disconnectService = ServiceRegister::getService(DisconnectService::class);
            $connectedStores = $this->getStoreService()->getConnectedStores();

            foreach ($connectedStores as $store) {
                StoreContext::doWithStore(
                    $store,
                    function () use ($disconnectService) {
                        $disconnectService->disconnect();
                    }
                );
            }
        } catch (Exception $e) {
            Logger::logWarning('Failed to disconnect merchant account because ' . $e->getMessage());
        }
    }

    /**
     * Removes images for payment methods and adyen giving for all connected shops.
     *
     * @return void
     *
     * @throws Exception
     */
    private function removeImages(): void
    {
        $connectedStores = $this->getStoreService()->getConnectedStores();
        foreach ($connectedStores as $store) {
            StoreContext::doWithStore(
                $store,
                function () {
                    $this->doRemoveImages();
                }
            );
        }
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    private function doRemoveImages(): void
    {
        $storeId = StoreContext::getInstance()->getStoreId();
        foreach ($this->getPaymentMethodConfigRepository()->getConfiguredPaymentMethods() as $paymentMethod) {
            ImageHandler::removeImage($paymentMethod->getMethodId(), $storeId);
        }
        ImageHandler::removeImage('adyen-giving-logo-store-' . $storeId, $storeId);
        ImageHandler::removeImage('adyen-giving-background-store-' . $storeId, $storeId);
        ImageHandler::removeDirectoryForStore($storeId);
    }

    /**
     * Create database tables for Adyen.
     * If creation fails Exception is thrown.
     *
     * @return void
     *
     * @throws Exception
     */
    private function createTables(): void
    {
        $this->createTable(self::ADYEN_ENTITY, 9);
        $this->createTable(self::ADYEN_NOTIFICATIONS, 5);
        $this->createTable(self::ADYEN_TRANSACTION_LOG, 4);
        $this->createTable(self::ADYEN_QUEUE, 9);
    }

    /**
     * @param string $tableName
     * @param int $indexNumber
     *
     * @return void
     *
     * @throws Exception
     */
    private function createTable(string $tableName, int $indexNumber): void
    {
        $createdTable = DatabaseHandler::createTable($tableName, $indexNumber);

        if (!$createdTable) {
            throw new Exception('Adyen plugin failed to create table: ' . $tableName);
        }
    }

    /**
     * Registers module Admin controllers.
     *
     * @return void
     *
     * @throws Exception
     */
    private function addControllers(): void
    {
        foreach (self::$controllers as $controller) {
            $this->addController($controller);
        }
    }

    /**
     * Registers Admin controller.
     *
     * @param string $name Controller name
     * @param int $parentId ID of parent controller
     *
     * @return void
     *
     * @throws Exception
     */
    private function addController(string $name, int $parentId = -1): void
    {
        $tab = new Tab();

        $tab->active = 1;
        $tab->name[(int)Configuration::get('PS_LANG_DEFAULT')] = $this->module->name;
        $tab->class_name = $name;
        $tab->module = $this->module->name;
        $tab->id_parent = $parentId;
        $success = $tab->add();

        if (!$success) {
            throw new Exception('Adyen plugin failed to register controller: ' . $name);
        }
    }

    /**
     * Call functions with arguments given as second parameter
     *
     * @param callable $handler Callback of function
     * @param string $arg Argument of function
     *
     * @return mixed|void Return value of the called handler
     */
    private function call(callable $handler, string $arg)
    {
        return call_user_func($handler, $arg);
    }

    /**
     * Registers module hooks.
     *
     * @return void
     *
     * @throws Exception
     */
    private function addHooks(): void
    {
        foreach (self::$deprecated_hooks as $hook) {
            if ($this->module->isRegisteredInHook($hook)) {
                $result = $this->module->unregisterHook($hook);
                if (!$result) {
                    throw new Exception('Adyen plugin failed to unregister hook: ' . $hook);
                }
            }
        }

        foreach (array_merge(self::$hooks, $this->getVersionHandler()->hooks()) as $hook) {
            $result = $this->module->registerHook($hook);
            if (!$result) {
                throw new Exception('Adyen plugin failed to register hook: ' . $hook);
            }
        }
    }

    /**
     * Adds/updates Adyen custom order states.
     *
     * @param string $name
     * @param string $color
     *
     * @return bool
     *
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    private function addCustomOrderState(string $name, string $color): void
    {
        $statusId = $this->getAllPrestaShopStatuses()[$name] ?? null;

        if ($statusId) {
            $orderState = new OrderState($statusId);
            $orderState->deleted = false;

            $success = $orderState->update();
            if (!$success) {
                throw new Exception('Adyen plugin failed to delete order state: ' . $name);
            }

            return;
        }

        $orderState = new OrderState();
        $orderState->name = [
            1 => $name,
            2 => $name
        ];

        $orderState->color = $color;
        $orderState->hidden = false;
        $orderState->delivery = false;
        $orderState->logable = false;
        $orderState->invoice = false;
        $orderState->module_name = $this->module->name;

        $success = $orderState->add();
        if ($success) {
            self::$allPrestaShopStatuses[$name] = (string)$orderState->id;
        }

        if (!$success) {
            throw new Exception('Adyen plugin failed to add order state: ' . $name);
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     *
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    private function deactivateCustomOrderState(string $name): void
    {
        $statusId = $this->getAllPrestaShopStatuses()[$name] ?? null;

        if (!$statusId) {
            return;
        }

        $orderState = new OrderState($statusId);

        if ($orderState->module_name === $this->module->name) {
            $orderState->deleted = true;

            $success = $orderState->update();

            if (!$success) {
                throw new Exception('Adyen plugin failed to delete order state: ' . $name);
            }
        }
    }

    /**
     * Drop database tables for Adyen.
     *
     * @return void
     *
     * @throws Exception
     */
    private function dropTables(): void
    {
        $this->dropTable(self::ADYEN_ENTITY);
        $this->dropTable(self::ADYEN_NOTIFICATIONS);
        $this->dropTable(self::ADYEN_TRANSACTION_LOG);
        $this->dropTable(self::ADYEN_QUEUE);
    }

    /**
     * @param string $tableName
     *
     * @return void
     *
     * @throws Exception
     */
    private function dropTable(string $tableName): void
    {
        $createdTable = DatabaseHandler::dropTable($tableName);

        if (!$createdTable) {
            throw new Exception('Adyen plugin failed to drop table: ' . $tableName);
        }
    }

    /**
     * @return StoreService
     */
    private function getStoreService(): StoreServiceInterface
    {
        return ServiceRegister::getService(StoreServiceInterface::class);
    }

    /**
     * @return PaymentMethodConfigRepository
     */
    private function getPaymentMethodConfigRepository(): PaymentMethodConfigRepository
    {
        return ServiceRegister::getService(PaymentMethodConfigRepository::class);
    }

    /**
     * Returns Version175 instance if PrestaShop version is < than 1.7.7.
     * Otherwise instance of Version177 is returned.
     *
     * @return VersionHandler
     */
    private function getVersionHandler(): VersionHandler
    {
        return ServiceRegister::getService(VersionHandler::class);
    }

    /**
     * @param string $overridePath
     *
     * @return bool
     */
    private function canAdyenOverride(string $overridePath): bool
    {
        $content = \Tools::file_get_contents($overridePath);

        return $content === false || preg_match('/function __construct/', $content) === 0;
    }

    /**
     * Returns all presta shop statuses: active and deleted ones.
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    private function getAllPrestaShopStatuses(): array
    {
        if (!static::$allPrestaShopStatuses) {
            static::$allPrestaShopStatuses = array_column(
                $this->getPrestaStatusesFromDatabase(),
                'id_order_state',
                'name'
            );
        }

        return static::$allPrestaShopStatuses;
    }

    /**
     * This function must be used for fetching OrderStates since function OrderStates::getOrderStates does not fetch already deleted order states.
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    private function getPrestaStatusesFromDatabase(): array
    {
        return Db::getInstance()->executeS(
            '
            SELECT *
            FROM `' . _DB_PREFIX_ . 'order_state` os
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = ' . 1 . ')' .
            ' ORDER BY `name` ASC'
        );
    }
}
