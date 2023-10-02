<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\InfoSettings\Models\SystemInfo;
use Adyen\Core\BusinessLogic\Domain\Integration\SystemInfo\SystemInfoService as SystemInfoServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Configuration\Configuration;

/**
 * Class SystemInfoService
 *
 * @package AdyenPayment\Integration
 */
class SystemInfoService implements SystemInfoServiceInterface
{
    /**
     * @var ConfigService
     */
    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @inheritDoc
     * @throws \PrestaShopException
     */
    public function getSystemInfo(): SystemInfo
    {
        return new SystemInfo(
            _PS_VERSION_,
            \Module::getInstanceByName('adyenofficial')->version,
            \Context::getContext()->shop->theme_name,
            \Tools::getShopProtocol() . \Context::getContext()->shop->domain,
            \Context::getContext()->link->getAdminLink('AdminLogin'),
            $this->configuration->getAsyncProcessUrl('test'),
            'mysql',
            \Db::getInstance()->getVersion()
        );
    }
}
