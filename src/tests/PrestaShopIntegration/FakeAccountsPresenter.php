<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

/**
 * Class FakeAccountsPresenter
 */
class FakeAccountsPresenter
{
    /**
     * @var array
     */
    private $payload;

    /**
     * @param array $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * @param string|null $psxName
     *
     * @return array
     */
    public function present(?string $psxName = null): array
    {
        return $this->payload;
    }
}
