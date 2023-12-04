<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\BusinessLogic\Domain\Webhook\Repositories\WebhookConfigRepository;
use Adyen\Core\Infrastructure\ServiceRegister;

/**
 * Class CreateWebhooksSeedDataService
 *
 * @package AdyenPayment\E2ETest\Services
 */
class CreateWebhooksSeedDataService extends BaseCreateSeedDataService
{
    /**
     * Returns username, password and hmac values from database
     *
     * @throws \Exception
     */
    public function getWebhookAuthorizationData(): array
    {
        $webhookConfig = $this->getWebhookConfigRepository()->getWebhookConfig();
        $authData = [];
        if ($webhookConfig){
            $authData['username'] = $webhookConfig->getUsername();
            $authData['password'] = $webhookConfig->getPassword();
            $authData['hmac'] = $webhookConfig->getHmac();
        }

        return $authData;
    }

    /**
     * Returns WebhookConfigRepository instance
     *
     * @return WebhookConfigRepository
     */
    private function getWebhookConfigRepository(): WebhookConfigRepository
    {
        return ServiceRegister::getService(WebhookConfigRepository::class);
    }

}