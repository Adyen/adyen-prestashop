<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Integration\Version\VersionService as VersionServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Version\Models\VersionInfo;

/**
 * Class VersionInfoService
 *
 * @package AdyenPayment\Integration
 */
class VersionInfoService implements VersionServiceInterface
{
    /**
     * @inheritDoc
     */
    public function getVersionInfo(): VersionInfo
    {
        return new VersionInfo(
            \Module::getInstanceByName('adyenofficial')->version,
            '5.0.0'
        );
    }
}
