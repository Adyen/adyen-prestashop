<?php

namespace AdyenPayment\Tests\PrestaShopIntegration;

use RuntimeException;

/**
 * Class FakeAccountsFacade
 */
class FakeAccountsFacade
{
    /**
     * @var array
     */
    private $presenterPayload;

    /**
     * @var string
     */
    private $accountsCdn;

    /**
     * @param array $presenterPayload
     * @param string $accountsCdn
     */
    public function __construct(array $presenterPayload, string $accountsCdn)
    {
        $this->presenterPayload = $presenterPayload;
        $this->accountsCdn = $accountsCdn;
    }

    /**
     * @return FakeAccountsPresenter
     */
    public function getPsAccountsPresenter(): FakeAccountsPresenter
    {
        return new FakeAccountsPresenter($this->presenterPayload);
    }

    /**
     * @return FakeAccountsService
     *
     * @throws RuntimeException
     */
    public function getPsAccountsService(): FakeAccountsService
    {
        if ($this->accountsCdn === '') {
            throw new RuntimeException('Module not installed : ps_accounts');
        }

        return new FakeAccountsService($this->accountsCdn);
    }
}
