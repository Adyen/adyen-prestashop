<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

use RuntimeException;

/**
 * Class FailingServiceModule
 */
class FailingServiceModule extends \AdyenOfficial
{
    public function __construct()
    {
        $this->name = 'adyenofficial';
    }

    /**
     * {@inheritdoc}
     */
    public function getService(string $serviceName): ?object
    {
        throw new RuntimeException('Service container unavailable: ' . $serviceName);
    }
}
