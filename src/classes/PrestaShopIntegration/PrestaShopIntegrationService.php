<?php

namespace AdyenPayment\Classes\PrestaShopIntegration;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Prestashop\ModuleLibMboInstaller\DependencyBuilder;

/**
 * Class PrestaShopIntegrationService
 */
class PrestaShopIntegrationService
{
    public const PS_ACCOUNTS_MODULE = 'ps_accounts';

    public const PS_EVENTBUS_MODULE = 'ps_eventbus';

    public const ACCOUNTS_INSTALLER_SERVICE = 'adyenofficial.ps_accounts_installer';

    public const ACCOUNTS_FACADE_SERVICE = 'adyenofficial.ps_accounts_facade';

    public const MODULE_MANAGER_BUILDERS = [
        'PrestaShop\\PrestaShop\\Core\\Addon\\Module\\ModuleManagerBuilder',
        'PrestaShop\\PrestaShop\\Core\\Module\\ModuleManagerBuilder',
    ];

    /**
     * @var \AdyenOfficial
     */
    private $module;

    /**
     * @param \AdyenOfficial $module
     */
    public function __construct(\AdyenOfficial $module)
    {
        $this->module = $module;
    }

    /**
     * Installs, and enables when needed, the ps_accounts module.
     *
     * @return bool
     */
    public function provisionPsAccounts(): bool
    {
        try {
            $installer = $this->module->getService(self::ACCOUNTS_INSTALLER_SERVICE);

            if (!$installer->install()) {
                $this->log(
                    'PrestaShop Account (ps_accounts) could not be auto-installed. Its module files are not '
                    . 'present on this host, or the current employee lacks the permission to install modules. '
                    . 'Install "PrestaShop Accounts" manually to enable the account panel.'
                );

                return true;
            }

            if (!$installer->isModuleEnabled()) {
                $this->enableModule(self::PS_ACCOUNTS_MODULE);
            }
        } catch (\Throwable $e) {
            $this->log('PrestaShop Account (ps_accounts) setup skipped. Error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Installs, enables or upgrades the ps_eventbus module required by CloudSync.
     *
     * @return bool
     */
    public function provisionPsEventBus(): bool
    {
        try {
            $moduleManager = $this->getModuleManager();

            if (!$moduleManager) {
                $this->log(
                    'PrestaShop CloudSync (ps_eventbus) setup skipped because no ModuleManagerBuilder is '
                    . 'available on PrestaShop ' . _PS_VERSION_ . '.'
                );

                return true;
            }

            $justInstalled = false;

            if (!$moduleManager->isInstalled(self::PS_EVENTBUS_MODULE)) {
                $moduleManager->install(self::PS_EVENTBUS_MODULE);
                $justInstalled = true;
            }

            if (!$moduleManager->isEnabled(self::PS_EVENTBUS_MODULE)) {
                $moduleManager->enable(self::PS_EVENTBUS_MODULE);
            }

            if ($justInstalled) {
                return true;
            }

            try {
                $moduleManager->upgrade(self::PS_EVENTBUS_MODULE);
            } catch (\Throwable $e) {
                $this->log(
                    'PrestaShop CloudSync (ps_eventbus) upgrade skipped, the module remains at its installed '
                    . 'version. Error: ' . $e->getMessage()
                );
            }
        } catch (\Throwable $e) {
            $this->log('PrestaShop CloudSync (ps_eventbus) setup skipped. Error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Returns the Smarty variables required by the configuration page for both integrations.
     *
     * @return array
     */
    public function getConfigurationPageContext(): array
    {
        return array_merge(
            $this->getDependencyContext(),
            $this->getPsAccountsContext()
        );
    }

    /**
     * Returns the context for the dependency resolver installing the modules declared in
     * module_dependencies.json.
     *
     * Unlike the integration guidelines, the resolver is rendered alongside the Adyen
     * configuration page rather than in place of it. Both companion modules are optional
     * enhancements, so their absence must never keep a merchant from configuring payments.
     *
     * @return array
     */
    public function getDependencyContext(): array
    {
        try {
            $dependencyBuilder = $this->buildDependencyBuilder();

            if ($dependencyBuilder->areDependenciesMet()) {
                return [];
            }

            return [
                'requiredDependencies' => $dependencyBuilder->handleDependencies(),
                'hasRequiredDependencies' => false,
            ];
        } catch (\Throwable $e) {
            $this->log(
                'PrestaShop dependency resolver unavailable, the companion module prompt is not shown. '
                . 'Error: ' . $e->getMessage()
            );

            return [];
        }
    }

    /**
     * Returns the PrestaShop Account association context.
     *
     * @return array
     */
    public function getPsAccountsContext(): array
    {
        try {
            $facade = $this->module->getService(self::ACCOUNTS_FACADE_SERVICE);
            $context = $facade->getPsAccountsPresenter()->present($this->module->name);

            try {
                $cdnUrl = $facade->getPsAccountsService()->getAccountsCdn();
            } catch (\Throwable $e) {
                $cdnUrl = '';
            }

            if (empty($cdnUrl)) {
                $this->log(
                    'PrestaShop Account panel hidden because ps_accounts is not installed, not enabled, '
                    . 'or below the required version.'
                );

                return [];
            }

            return [
                'contextPsAccounts' => $context,
                'urlAccountsCdn' => $cdnUrl,
            ];
        } catch (\Throwable $e) {
            $this->log('PrestaShop Account context unavailable. Error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Enables a companion module, tolerating hosts where enabling is not permitted.
     *
     * @param string $moduleName
     *
     * @return void
     */
    protected function enableModule(string $moduleName): void
    {
        try {
            $moduleManager = $this->getModuleManager();

            if ($moduleManager) {
                $moduleManager->enable($moduleName);
            }
        } catch (\Throwable $e) {
            $this->log('Could not enable ' . $moduleName . '. Error: ' . $e->getMessage());
        }
    }

    /**
     * @return DependencyBuilder
     */
    protected function buildDependencyBuilder(): DependencyBuilder
    {
        return new DependencyBuilder($this->module);
    }

    /**
     * Builds a PrestaShop ModuleManager, resolving the builder across supported PrestaShop
     * versions.
     *
     * @return object|null
     */
    protected function getModuleManager(): ?object
    {
        foreach (self::MODULE_MANAGER_BUILDERS as $builderClass) {
            if (class_exists($builderClass)) {
                return $builderClass::getInstance()->build();
            }
        }

        return null;
    }

    /**
     * @param string $message
     *
     * @return void
     */
    protected function log(string $message): void
    {
        \PrestaShopLogger::addLog('Adyen: ' . $message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING);
    }
}
