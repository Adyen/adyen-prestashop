<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Integration\Version\VersionService as VersionServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Version\Models\VersionInfo;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Proxies\GithubProxy;

/**
 * Class VersionInfoService
 *
 * @package AdyenPayment\Integration
 */
class VersionInfoService implements VersionServiceInterface
{
    /**
     * @inheritDoc
     *
     * @throws HttpRequestException
     */
    public function getVersionInfo(): VersionInfo
    {
        $version = \Module::getInstanceByName('adyenofficial')->version;

        return new VersionInfo($version, $this->getLatestVersion());
    }

    /**
     * @return string
     *
     * @throws HttpRequestException
     */
    private function getLatestVersion(): string
    {
        return $this->getGithubProxy()->getLatestVersion();
    }

    /**
     * @return GithubProxy
     */
    private function getGithubProxy(): GithubProxy
    {
        return ServiceRegister::getService(GithubProxy::class);
    }
}
