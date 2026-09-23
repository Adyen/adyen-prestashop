<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

use PHPUnit\Framework\TestCase;

/**
 * Class PrestaShopIntegrationServiceTest
 */
class PrestaShopIntegrationServiceTest extends TestCase
{
    public function testProvisioningAccountsSurvivesAnUnavailableContainer(): void
    {
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());

        $this->assertTrue($service->provisionPsAccounts());
    }

    public function testProvisioningEventBusSurvivesAMissingModuleManager(): void
    {
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->moduleManager = null;

        $this->assertTrue($service->provisionPsEventBus());
    }

    public function testEventBusIsInstalledAndEnabledWhenAbsentAndNotUpgraded(): void
    {
        $moduleManager = new FakeModuleManager();
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->moduleManager = $moduleManager;

        $service->provisionPsEventBus();

        $this->assertSame(['install:ps_eventbus', 'enable:ps_eventbus'], $moduleManager->calls);
    }

    public function testAFreshlyInstalledAndEnabledEventBusIsLeftAlone(): void
    {
        $moduleManager = new FakeModuleManager();
        $moduleManager->enableOnInstall = true;
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->moduleManager = $moduleManager;

        $service->provisionPsEventBus();

        $this->assertSame(['install:ps_eventbus'], $moduleManager->calls);
    }

    public function testEventBusIsEnabledAndUpgradedWhenInstalledButDisabled(): void
    {
        $moduleManager = new FakeModuleManager();
        $moduleManager->installed = true;
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->moduleManager = $moduleManager;

        $service->provisionPsEventBus();

        $this->assertSame(['enable:ps_eventbus', 'upgrade:ps_eventbus'], $moduleManager->calls);
    }

    public function testAFailingEventBusUpgradeDoesNotFailProvisioning(): void
    {
        $moduleManager = new FakeModuleManager();
        $moduleManager->installed = true;
        $moduleManager->enabled = true;
        $moduleManager->failUpgrade = true;
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->moduleManager = $moduleManager;

        $this->assertTrue($service->provisionPsEventBus());
    }

    public function testAccountsContextExposesTheWebComponentWhenAvailable(): void
    {
        $module = new StubServiceModule(
            new FakeAccountsFacade(['psAccountsIsInstalled' => true], 'https://cdn.example/accounts.js')
        );

        $context = (new TestablePrestaShopIntegrationService($module))->getPsAccountsContext();

        $this->assertSame(['contextPsAccounts', 'urlAccountsCdn'], array_keys($context));
        $this->assertSame('https://cdn.example/accounts.js', $context['urlAccountsCdn']);
    }

    public function testAccountsSectionIsHiddenWithoutACdnRatherThanPromptingDirectly(): void
    {
        $module = new StubServiceModule(
            new FakeAccountsFacade(['psAccountsIsInstalled' => false], '')
        );

        $this->assertSame([], (new TestablePrestaShopIntegrationService($module))->getPsAccountsContext());
    }

    public function testAccountsContextIsEmptyWhenTheFacadeCannotBeResolved(): void
    {
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());

        $this->assertSame([], $service->getPsAccountsContext());
    }

    public function testDependencyContextIsEmptyWhenDependenciesAreMet(): void
    {
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->dependencyBuilder = new FakeDependencyBuilder(true);

        $this->assertSame([], $service->getDependencyContext());
    }

    public function testDependenciesAreNotHandledWhenTheyAreAlreadyMet(): void
    {
        $builder = new FakeDependencyBuilder(true);
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->dependencyBuilder = $builder;

        $service->getDependencyContext();

        $this->assertSame(0, $builder->handleDependenciesCalls);
    }

    public function testDependencyContextExposesTheResolverPayloadWhenDependenciesAreMissing(): void
    {
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->dependencyBuilder = new FakeDependencyBuilder(
            false,
            false,
            ['module_name' => 'adyenofficial', 'dependencies' => ['ps_accounts' => ['installed' => false]]]
        );

        $context = $service->getDependencyContext();

        $this->assertSame(['requiredDependencies', 'hasRequiredDependencies'], array_keys($context));
        $this->assertFalse($context['hasRequiredDependencies']);
        $this->assertSame('adyenofficial', $context['requiredDependencies']['module_name']);
    }

    public function testAnUnavailableResolverNeverBlocksTheConfigurationPage(): void
    {
        $service = new TestablePrestaShopIntegrationService(new FailingServiceModule());
        $service->dependencyBuilder = new FakeDependencyBuilder(false, true);

        $this->assertSame([], $service->getDependencyContext());
    }
}
