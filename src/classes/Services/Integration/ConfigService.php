<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Configuration\Configuration;
use AdyenPayment\Classes\Utility\Url;

/**
 * Class ConfigurationService
 *
 * @package AdyenPayment\Integration\Configuration
 */
class ConfigService extends Configuration
{
    private const INTEGRATION_NAME = 'Presta Shop';

    const MIN_LOG_LEVEL = 1;

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
        return '5.0.0';
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
        return 'AdyenPayment';
    }

    /**
     * @return string
     */
    public function getPluginVersion(): string
    {
        // TODO: Implement getPluginVersion() method.
        return '';
    }
}
