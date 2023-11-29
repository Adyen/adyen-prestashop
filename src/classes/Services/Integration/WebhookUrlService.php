<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Integration\Webhook\WebhookUrlService as WebhookUrlServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\Infrastructure\Configuration\ConfigurationManager;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Utility\Url;

/**
 * Class WebhookUrlService
 *
 * @package AdyenPayment\Integration
 */
class WebhookUrlService implements WebhookUrlServiceInterface
{
    /**
     * Returns web-hook callback URL for current system.
     *
     * @return string
     * @throws QueryFilterInvalidParamException
     */
    public function getWebhookUrl(): string
    {
        $url = Url::getFrontUrl('webhook', ['storeId' => StoreContext::getInstance()->getStoreId()]);
        $testHostname = $this->getConfigurationManager()->getConfigValue('testHostname');
        if($testHostname){
            $url = str_replace('localhost', $testHostname, $url);
        }

        return $url;
    }

    /**
     * @return ConfigurationManager
     *
     */
    private function getConfigurationManager(): ConfigurationManager
    {
        return ServiceRegister::getService(ConfigurationManager::CLASS_NAME);
    }
}
