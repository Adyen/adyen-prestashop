<?php

namespace AdyenPayment\Tests;


use Adyen\Core\Infrastructure\Configuration\Configuration;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Services\Integration\ConfigService;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    /** @var Configuration */
    public $configService;

    public function setUp(): void
    {
        ConfigService::resetInstance();
        $this->configService = ConfigService::getInstance();
        $me = $this;
        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () use ($me) {
                return $me->configService;
            }
        );
    }

    public function testCorrectVersion(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        $this->assertEquals($composer['version'], $this->configService->getIntegrationVersion());
        $this->assertEquals('Presta Shop', $this->configService->getIntegrationName());
    }
}
