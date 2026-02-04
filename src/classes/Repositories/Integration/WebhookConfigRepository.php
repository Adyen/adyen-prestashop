<?php

namespace AdyenPayment\Classes\Repositories\Integration;

use Adyen\Core\BusinessLogic\DataAccess\Webhook\Entities\WebhookConfig as WebhookConfigEntity;
use Adyen\Core\BusinessLogic\DataAccess\Webhook\Repositories\WebhookConfigRepository as BaseWebhookConfigRepository;
use Adyen\Core\BusinessLogic\Domain\Webhook\Models\WebhookConfig;
use AdyenPayment\Classes\Services\Encryptor;

/**
 * Class WebhookConfigRepository
 */
class WebhookConfigRepository extends BaseWebhookConfigRepository
{
    /**
     * @inerhitDoc
     *
     * @throws \Exception
     */
    public function getWebhookConfig(): ?WebhookConfig
    {
        $config = parent::getWebhookConfig();

        if ($config === null) {
            return null;
        }

        $config->setPassword(Encryptor::decryptData($config->getPassword()));
        $config->setHmac(Encryptor::decryptData($config->getHmac()));

        return $config;
    }

    /**
     * {@inheritDoc}
     */
    public function setWebhookConfig(WebhookConfig $config): void
    {
        $existingConfig = $this->getWebhookConfigEntity();
        $config->setPassword(Encryptor::encryptData($config->getPassword()));
        $config->setHmac(Encryptor::encryptData($config->getHmac()));

        if ($existingConfig) {
            $existingConfig->setWebhookConfig($config);
            $this->repository->update($existingConfig);

            return;
        }

        $entity = new WebhookConfigEntity();
        $entity->setWebhookConfig($config);
        $entity->setStoreId($this->storeContext->getStoreId());
        $this->repository->save($entity);
    }
}
