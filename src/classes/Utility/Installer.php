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
use AdyenPayment\Classes\Services\AdyenOrderStatusMapping;
use AdyenPayment\Classes\Services\ImageHandler;
use AdyenPayment\Classes\Services\Integration\StoreService;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Configuration;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\OrderState;
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
     * @return bool Installation status
     *
     * @throws RepositoryClassException
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    public function install(): bool
    {
        Bootstrap::init();

        return (
            $this->createTables() &&
            $this->addControllers() &&
            $this->addHooks() &&
            $this->addCustomOrderStates()
        );
    }

    /**
     * Drop database tables, remove hooks, controller, order states and configuration values
     *
     * @return bool Uninstallation status
     *
     * @throws RepositoryClassException
     *
     * @throws Exception
     */
    public function uninstall(): bool
    {
        Bootstrap::init();
        $this->removeImages();
        ImageHandler::removeAdyenDirectory();
        $this->disconnect();

        return (
            $this->dropTables() &&
            $this->removeControllers() &&
            $this->removeHooks()
        );
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
     * @return bool Controller deletion status
     */
    public function removeControllers(): bool
    {
        try {
            $tabs = Tab::getCollectionFromModule($this->module->name);
            if ($tabs && count($tabs)) {
                foreach ($tabs as $tab) {
                    $tab->delete();
                }
            }

            return true;
        } catch (PrestaShopException $exception) {
            Logger::logError('Error removing controller! Error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Adds controllers and hooks.
     *
     * @return bool
     */
    public function addControllersAndHooks(): bool
    {
        return $this->addHooks() && $this->addControllers();
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
     *
     * @return bool Table creation status
     */
    private function createTables(): bool
    {
        return (
            DatabaseHandler::createTable(self::ADYEN_ENTITY, 9) &&
            DatabaseHandler::createTable(self::ADYEN_NOTIFICATIONS, 5) &&
            DatabaseHandler::createTable(self::ADYEN_TRANSACTION_LOG, 4) &&
            DatabaseHandler::createTable(self::ADYEN_QUEUE, 9)
        );
    }

    /**
     * Registers module Admin controllers.
     *
     * @return bool Controller addition status
     */
    private function addControllers(): bool
    {
        $result = true;
        foreach (self::$controllers as $controller) {
            $result = $result && $this->addController($controller);
        }

        return $result;
    }

    /**
     * Registers Admin controller.
     *
     * @param string $name Controller name
     * @param int $parentId ID of parent controller
     *
     * @return bool Controller addition status
     */
    private function addController(string $name, int $parentId = -1): bool
    {
        $tab = new Tab();

        $tab->active = 1;
        $tab->name[(int)Configuration::get('PS_LANG_DEFAULT')] = $this->module->name;
        $tab->class_name = $name;
        $tab->module = $this->module->name;
        $tab->id_parent = $parentId;
        $tab->add();

        return true;
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
     * @return bool Hook addition status
     */
    private function addHooks(): bool
    {
        $result = true;

        foreach (self::$deprecated_hooks as $hook) {
            $result = $result && $this->module->unregisterHook($hook);
        }

        foreach (array_merge(self::$hooks, $this->getVersionHandler()->hooks()) as $hook) {
            $result = $result && $this->module->registerHook($hook);
        }

        return $result;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    private function addCustomOrderStates(): bool
    {
        return $this->addCustomOrderState('Pending', '#4169E1')
            && $this->addCustomOrderState('Partially refunded', '#6F8C9F')
            && $this->addCustomOrderState('Chargeback', '#E74C3C');
    }

    /**
     * @param string $name
     * @param string $color
     *
     * @return bool
     *
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    private function addCustomOrderState(string $name, string $color): bool
    {
        if (!AdyenOrderStatusMapping::getPrestaShopOrderStatusId($name)) {
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

            return $orderState->add();
        }

        return true;
    }

    /**
     * Drop database tables for Adyen.
     *
     * @return bool Table deletion status
     */
    private function dropTables(): bool
    {
        return (
            DatabaseHandler::dropTable(self::ADYEN_ENTITY) &&
            DatabaseHandler::dropTable(self::ADYEN_NOTIFICATIONS) &&
            DatabaseHandler::dropTable(self::ADYEN_TRANSACTION_LOG) &&
            DatabaseHandler::dropTable(self::ADYEN_QUEUE)
        );
    }

    /**
     * Unregisters module hooks.
     *
     * @return bool Hook deletion status
     */
    private function removeHooks(): bool
    {
        $result = true;
        foreach (array_merge(self::$hooks, $this->getVersionHandler()->hooks()) as $hook) {
            $result = $result && $this->module->unregisterHook($hook);
        }

        return $result;
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
}
