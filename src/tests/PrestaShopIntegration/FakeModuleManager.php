<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

use RuntimeException;

/**
 * Class FakeModuleManager
 */
class FakeModuleManager
{
    /**
     * @var bool
     */
    public $installed = false;

    /**
     * @var bool
     */
    public $enabled = false;

    /**
     * @var bool
     */
    public $failUpgrade = false;

    /**
     * @var string[]
     */
    public $calls = [];

    public function isInstalled(string $name): bool
    {
        return $this->installed;
    }

    public function isEnabled(string $name): bool
    {
        return $this->enabled;
    }

    public function install(string $name): bool
    {
        $this->calls[] = 'install:' . $name;
        $this->installed = true;

        return true;
    }

    public function enable(string $name): bool
    {
        $this->calls[] = 'enable:' . $name;
        $this->enabled = true;

        return true;
    }

    public function upgrade(string $name): bool
    {
        $this->calls[] = 'upgrade:' . $name;

        if ($this->failUpgrade) {
            throw new RuntimeException('Upgrade of ' . $name . ' failed.');
        }

        return true;
    }
}
