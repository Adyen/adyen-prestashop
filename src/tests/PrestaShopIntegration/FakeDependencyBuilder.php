<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

use Prestashop\ModuleLibMboInstaller\DependencyBuilder;
use RuntimeException;

/**
 * Class FakeDependencyBuilder
 */
class FakeDependencyBuilder extends DependencyBuilder
{
    /**
     * @var bool
     */
    public $dependenciesMet;

    /**
     * @var bool
     */
    public $throws;

    /**
     * @var array
     */
    public $payload;

    /**
     * @var int
     */
    public $handleDependenciesCalls = 0;

    /**
     * @param bool $dependenciesMet
     * @param bool $throws
     * @param array $payload
     */
    public function __construct(bool $dependenciesMet, bool $throws = false, array $payload = [])
    {
        $this->dependenciesMet = $dependenciesMet;
        $this->throws = $throws;
        $this->payload = $payload ?: ['module_name' => 'adyenofficial', 'dependencies' => []];
    }

    /**
     * {@inheritdoc}
     */
    public function areDependenciesMet(): bool
    {
        if ($this->throws) {
            throw new RuntimeException('Unable to retrieve Symfony AppKernel.');
        }

        return $this->dependenciesMet;
    }

    /**
     * {@inheritdoc}
     */
    public function handleDependencies(): array
    {
        ++$this->handleDependenciesCalls;

        return $this->payload;
    }
}
