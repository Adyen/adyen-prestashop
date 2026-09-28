<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

use AdyenPayment\Classes\PrestaShopIntegration\PrestaShopIntegrationService;
use Prestashop\ModuleLibMboInstaller\DependencyBuilder;
use RuntimeException;

/**
 * Class TestablePrestaShopIntegrationService
 */
class TestablePrestaShopIntegrationService extends PrestaShopIntegrationService
{
    /**
     * @var FakeModuleManager|null
     */
    public $moduleManager;

    /**
     * @var string[]
     */
    public $logs = [];

    /**
     * @var FakeDependencyBuilder|null
     */
    public $dependencyBuilder;

    /**
     * @var bool|null Null delegates to the real permission check.
     */
    public $canInstallModules = true;

    /**
     * {@inheritdoc}
     */
    protected function getModuleManager(): ?object
    {
        return $this->moduleManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildDependencyBuilder(): DependencyBuilder
    {
        if ($this->dependencyBuilder === null) {
            throw new RuntimeException('No dependency builder configured for this test.');
        }

        return $this->dependencyBuilder;
    }

    /**
     * {@inheritdoc}
     */
    protected function canInstallModules(): bool
    {
        return $this->canInstallModules ?? parent::canInstallModules();
    }

    /**
     * {@inheritdoc}
     */
    protected function log(string $message): void
    {
        $this->logs[] = $message;
    }
}
