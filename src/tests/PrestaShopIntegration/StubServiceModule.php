<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

/**
 * Class StubServiceModule
 */
class StubServiceModule extends \AdyenOfficial
{
    /**
     * @var object
     */
    private $service;

    /**
     * @param object $service
     */
    public function __construct($service)
    {
        $this->name = 'adyenofficial';
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    public function getService(string $serviceName): ?object
    {
        return $this->service;
    }
}
