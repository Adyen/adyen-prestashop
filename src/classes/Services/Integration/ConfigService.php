<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Configuration\Configuration;
use AdyenPayment\Classes\Utility\Url;

/**
 * Class ConfigurationService
 */
class ConfigService extends Configuration
{
    private const INTEGRATION_NAME = 'PrestaShop';

    public const MIN_LOG_LEVEL = 1;

    /**
     * @param $guid
     *
     * @return string
     */
    public function getAsyncProcessUrl($guid): string
    {
        $params = ['guid' => $guid];
        if ($this->isAutoTestMode()) {
            $params['auto-test'] = 1;
        }

        return Url::getFrontUrl('asyncprocess', $params);
    }

    /**
     * @return string
     */
    public function getIntegrationVersion(): string
    {
        return _PS_VERSION_;
    }

    /**
     * @return string
     */
    public function getIntegrationName(): string
    {
        return self::INTEGRATION_NAME;
    }

    /**
     * @return string
     */
    public function getPluginName(): string
    {
        $module = \Module::getInstanceByName('adyenofficial');

        return $module->displayName . ' ' . $this->getIntegrationName();
    }

    /**
     * @return string
     */
    public function getPluginVersion(): string
    {
        $module = \Module::getInstanceByName('adyenofficial');

        return $module->version;
    }
}
