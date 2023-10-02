<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Integration\Webhook\WebhookUrlService as WebhookUrlServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
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
     */
    public function getWebhookUrl(): string
    {
        return Url::getFrontUrl('webhook', ['storeId' => StoreContext::getInstance()->getStoreId()]);
    }
}
