<?php


use Adyen\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Webhook\Services\WebhookRegistrationService;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'Autoloader.php';

/**
 * Upgrades module to version 6.1.0.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 *
 * @throws PrestaShopException
 * @throws RepositoryClassException
 */
function upgrade_module_6_1_0(AdyenOfficial $module): bool
{
    Autoloader::setFileExt('.php');
    spl_autoload_register('Autoloader::loader');
    Shop::setContext(Shop::CONTEXT_ALL);

    Bootstrap::init();

    $shops = Shop::getShops();

    foreach ($shops as $shop) {
        if ($shop['id_shop'] === 0) {
            continue;
        }

        try {
            StoreContext::doWithStore(
                (string)$shop['id_shop'],
                static function () {
                    /** @var ConnectionService $connectionService */
                    $connectionService = ServiceRegister::getService(ConnectionService::class);
                    $connectionSettings = $connectionService->getConnectionData();
                    /** @var WebhookRegistrationService $webhookService */
                    $webhookService = ServiceRegister::getService(WebhookRegistrationService::class);

                    if ($connectionSettings) {
                        $merchantId = $connectionSettings->getActiveConnectionData()->getMerchantId();

                        $webhookService->update($merchantId);
                    }
                }
            );
        } catch (Exception $e) {
            Logger::logError('Migration to version 6.1.0 failed because: ' . $e->getMessage());

            return false;
        }
    }

    return true;
}