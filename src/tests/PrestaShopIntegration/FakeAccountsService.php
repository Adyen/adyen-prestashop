<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

/**
 * Class FakeAccountsService
 */
class FakeAccountsService
{
    /**
     * @var string
     */
    private $accountsCdn;

    /**
     * @param string $accountsCdn
     */
    public function __construct(string $accountsCdn)
    {
        $this->accountsCdn = $accountsCdn;
    }

    /**
     * @return string
     */
    public function getAccountsCdn(): string
    {
        return $this->accountsCdn;
    }
}
