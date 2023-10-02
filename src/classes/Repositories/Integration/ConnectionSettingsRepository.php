<?php

namespace AdyenPayment\Classes\Repositories\Integration;

use Adyen\Core\BusinessLogic\DataAccess\Connection\Entities\ConnectionSettings as ConnectionSettingsEntity;
use Adyen\Core\BusinessLogic\DataAccess\Connection\Repositories\ConnectionSettingsRepository as BaseConnectionSettingsRepository;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyConnectionDataException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyStoreException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidModeException;
use Adyen\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use Adyen\Core\BusinessLogic\Domain\Connection\Models\ConnectionSettings;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use AdyenPayment\Classes\Services\Encryptor;

/**
 * Class ConnectionSettingsRepository
 *
 * @package AdyenPayment\Classes\Repositories\Integration
 */
class ConnectionSettingsRepository extends BaseConnectionSettingsRepository
{
    /**
     * @inerhitDoc
     * @throws QueryFilterInvalidParamException
     * @throws \Exception
     */
    public function getConnectionSettings(): ?ConnectionSettings
    {
        $connectionSettings = parent::getConnectionSettings();

        if ($connectionSettings === null) {
            return null;
        }

        $connectionSettings->setTestData($this->decryptData($connectionSettings->getTestData()));
        $connectionSettings->setLiveData($this->decryptData($connectionSettings->getLiveData()));

        return $connectionSettings;
    }

    /**
     * @inerhitDoc
     *
     * @param ConnectionSettings $connectionSettings
     * @throws QueryFilterInvalidParamException
     * @throws EmptyConnectionDataException
     * @throws EmptyStoreException
     * @throws InvalidModeException
     */
    public function setConnectionSettings(ConnectionSettings $connectionSettings): void
    {
        $existingSettings = $this->getConnectionSettingsEntity();
        $settings = new ConnectionSettings(
            $connectionSettings->getStoreId(),
            $connectionSettings->getMode(),
            $connectionSettings->getTestData(),
            $connectionSettings->getLiveData()
        );

        $settings->setTestData($this->encryptData($settings->getTestData()));
        $settings->setLiveData($this->encryptData($settings->getLiveData()));

        if ($existingSettings) {
            $existingSettings->setConnectionSettings($settings);
            $this->repository->update($existingSettings);

            return;
        }

        $entity = new ConnectionSettingsEntity();
        $entity->setConnectionSettings($settings);
        $this->repository->save($entity);
    }

    /**
     * @inerhitDoc
     * @throws \Exception
     */
    public function getOldestConnectionSettings(): ?ConnectionSettings
    {
        $connectionSettings = parent::getOldestConnectionSettings();

        if ($connectionSettings === null) {
            return null;
        }

        $connectionSettings->setTestData($this->decryptData($connectionSettings->getTestData()));
        $connectionSettings->setLiveData($this->decryptData($connectionSettings->getLiveData()));

        return $connectionSettings;
    }

    /**
     * @inerhitDoc
     * @throws \Exception
     */
    public function getAllConnectionSettings(): array
    {
        $connectionSettings = parent::getAllConnectionSettings();

        return array_map(function ($connectionSetting) {
            $connectionSetting->setTestData($this->decryptData($connectionSetting->getTestData()));
            $connectionSetting->setLiveData($this->decryptData($connectionSetting->getLiveData()));

            return $connectionSetting;
        }, $connectionSettings);
    }

    /**
     * @param ConnectionData|null $data
     *
     * @return ConnectionData|null
     */
    private function encryptData(?ConnectionData $data): ?ConnectionData
    {
        return $data !== null ? new ConnectionData(
            Encryptor::encryptData($data->getApiKey()),
            $data->getMerchantId(),
            Encryptor::encryptData($data->getClientPrefix()),
            Encryptor::encryptData($data->getClientKey()),
            $data->getApiCredentials()
        ) : null;
    }

    /**
     * @param ConnectionData|null $data
     *
     * @return ConnectionData|null
     *
     * @throws \Exception
     */
    private function decryptData(?ConnectionData $data): ?ConnectionData
    {
        return $data !== null ? new ConnectionData(
            Encryptor::decryptData($data->getApiKey()),
            $data->getMerchantId(),
            Encryptor::decryptData($data->getClientPrefix()),
            Encryptor::decryptData($data->getClientKey()),
            $data->getApiCredentials()
        ) : null;
    }
}
