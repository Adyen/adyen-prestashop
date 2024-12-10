<?php

use Adyen\Core\BusinessLogic\AdyenAPI\Exceptions\ConnectionSettingsNotFoundException;
use Adyen\Core\BusinessLogic\AdyenAPI\Management\Connection\Http\Proxy;
use Adyen\Core\BusinessLogic\AdyenAPI\Management\ProxyFactory;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Proxies\PaymentsProxy;
use Adyen\Core\BusinessLogic\Domain\Connection\Enums\Mode;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyConnectionDataException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\EmptyStoreException;
use Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\InvalidModeException;
use Adyen\Core\BusinessLogic\Domain\Connection\Models\ApiCredentials;
use Adyen\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use Adyen\Core\BusinessLogic\Domain\Connection\Models\ConnectionSettings;
use Adyen\Core\BusinessLogic\Domain\Connection\Repositories\ConnectionSettingsRepository;
use Adyen\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\MethodAdditionalData\GooglePay;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\MethodAdditionalData\Oney;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\PaymentMethod;
use Adyen\Core\BusinessLogic\Domain\Payment\Services\PaymentService;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Core\Infrastructure\TaskExecution\Interfaces\TaskRunnerManager;
use Adyen\Core\Infrastructure\TaskExecution\QueueService;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\MigrateTransactionHistoryTask;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'Autoloader.php';


/**
 * Upgrades module to version 5.0.0.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 * @throws Exception
 */
function upgrade_module_5_0_0(AdyenOfficial $module): bool
{
    \AdyenPayment\Autoloader::setFileExt('.php');
    spl_autoload_register('\AdyenPayment\Autoloader::loader');

    $previousShopContext = Shop::getContext();
    Shop::setContext(ShopCore::CONTEXT_ALL);

    $installer = new \AdyenPayment\Classes\Utility\Installer($module);

    Bootstrap::init();
    try {
        $installer->install();

        Logger::logDebug('Upgrade to plugin v5.0.0 has started.');
        $installer->removeControllers();
        removeHooks($module);
        removeObsoleteFiles($module);

        $installer->addControllersAndHooks();
    } catch (Throwable $exception) {
        Logger::logError(
            'Adyen plugin migration to 5.0.0 failed. Reason: ' .
            $exception->getMessage() . ' .Trace: ' . $exception->getTraceAsString()
        );

        return false;
    }

    $manager = getTaskRunnerManager();
    $manager->halt();

    $migratedShops = migrateApiCredentials();
    migratePaymentMethodConfigs($migratedShops);
    getQueueService()->enqueue('general-migration', new MigrateTransactionHistoryTask());
    removeObsoleteData();

    spl_autoload_unregister('\AdyenPayment\Autoloader::loader');

    $module->enable();
    Shop::setContext(ShopCore::CONTEXT_SHOP, $previousShopContext);
    \Configuration::loadConfiguration();

    $manager->resume();

    return true;
}

/**
 * @return array
 *
 * @throws Exception
 */
function migrateApiCredentials(): array
{
    $shops = Shop::getShops();
    $migratedShops = [];

    foreach ($shops as $shop) {
        if ($shop['id_shop'] === 0) {
            continue;
        }

        StoreContext::doWithStore(
            (string)$shop['id_shop'],
            function () use ($shop, &$migratedShops) {
                try {
                    if (migrateApiCredentialsForShop()) {
                        $migratedShops[$shop['id_shop']] = $shop;
                    }
                } catch (Exception $e) {
                    Logger::logWarning('Failed to migrate connection for ' . $shop['id_shop']);
                }
            }
        );
    }

    return $migratedShops;
}

function migratePaymentMethodConfigs(array $migratedShops)
{
    foreach ($migratedShops as $key => $shop) {
        StoreContext::doWithStore($key, function () use ($key) {
            /** @var PaymentService $paymentService */
            $paymentService = ServiceRegister::getService(PaymentService::class);
            /** @var PaymentsProxy $checkoutProxy */
            $checkoutProxy = ServiceRegister::getService(PaymentsProxy::class);

            $availableMethods = $paymentService->getAvailableMethods();
            $settings = getConnectionService()->getConnectionData();
            $request = new \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodsRequest(
                $settings->getActiveConnectionData()->getMerchantId(),
                [
                    (string)PaymentMethodCode::facilyPay3x(),
                    (string)PaymentMethodCode::facilyPay4x(),
                    (string)PaymentMethodCode::facilyPay6x(),
                    (string)PaymentMethodCode::facilyPay10x(),
                    (string)PaymentMethodCode::facilyPay12x(),
                ]
            );
            $oneyInstallmentsMap = [
                (string)PaymentMethodCode::facilyPay3x() => '3',
                (string)PaymentMethodCode::facilyPay4x() => '4',
                (string)PaymentMethodCode::facilyPay6x() => '6',
                (string)PaymentMethodCode::facilyPay10x() => '10',
                (string)PaymentMethodCode::facilyPay12x() => '12',
            ];
            $oneyInstallments = $checkoutProxy->getAvailablePaymentMethods($request);
            $oneyEnabledInstallments = [];

            foreach ($oneyInstallments as $installment) {
                $oneyEnabledInstallments[] = $oneyInstallmentsMap[$installment];
            }

            $oneyIsConfigured = false;

            foreach ($availableMethods as $availableMethod) {
                if ($oneyIsConfigured && PaymentMethodCode::isOneyMethod($availableMethod->getCode())) {
                    continue;
                }

                $paymentMethod = new PaymentMethod(
                    $availableMethod->getMethodId(),
                    $availableMethod->getCode(),
                    $availableMethod->getName(),
                    $availableMethod->getLogo(),
                    true,
                    $availableMethod->getCurrencies(),
                    $availableMethod->getCountries(),
                    $availableMethod->getType(),
                    'Adyen ' . $availableMethod->getName(),
                    'none'
                );

                if (PaymentMethodCode::isOneyMethod($availableMethod->getCode())) {
                    $oneyIsConfigured = true;
                    $paymentMethod->setAdditionalData(new Oney($oneyEnabledInstallments));
                }

                if (PaymentMethodCode::googlePay()->equals($availableMethod->getCode()) ||
                    PaymentMethodCode::payWithGoogle()->equals($availableMethod->getCode())) {
                    $googleMerchantId = ConfigurationCore::get(
                        'ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID',
                        null,
                        null,
                        $key
                    );

                    if (empty($googleMerchantId)) {
                        continue;
                    }

                    $paymentMethod->setAdditionalData(
                        new GooglePay(
                            $googleMerchantId,
                            $settings->getActiveConnectionData()->getMerchantId()
                        )
                    );
                }

                $paymentService->saveMethodConfiguration($paymentMethod);
            }
        });
    }
}

/**
 * @return bool
 *
 * @throws ConnectionSettingsNotFoundException
 * @throws EmptyConnectionDataException
 * @throws EmptyStoreException
 * @throws InvalidModeException
 */
function migrateApiCredentialsForShop(): bool
{
    $storeId = StoreContext::getInstance()->getStoreId();
    $mode = ConfigurationCore::get('ADYEN_MODE', null, null, $storeId);
    $merchantAccount = ConfigurationCore::get('ADYEN_MERCHANT_ACCOUNT', null, null, $storeId);
    $ivLength = openssl_cipher_iv_length('aes-256-ctr');
    $testApiKeyEncrypted = ConfigurationCore::get('ADYEN_APIKEY_TEST', null, null, $storeId);
    $liveApiKeyEncrypted = ConfigurationCore::get('ADYEN_APIKEY_LIVE', null, null, $storeId);
    $hex = \Tools::substr($testApiKeyEncrypted, 0, $ivLength * 2);
    $iv = hex2bin($hex);
    $testApiKey = openssl_decrypt(
        \Tools::substr($testApiKeyEncrypted, $ivLength * 2),
        'aes-256-ctr',
        _COOKIE_KEY_,
        0,
        $iv
    );
    $hex = \Tools::substr($liveApiKeyEncrypted, 0, $ivLength * 2);
    $iv = hex2bin($hex);
    $liveApiKey = openssl_decrypt(
        Tools::substr($liveApiKeyEncrypted, $ivLength * 2),
        'aes-256-ctr',
        _COOKIE_KEY_,
        0,
        $iv
    );
    $liveUrlPrefix = ConfigurationCore::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX', null, null, $storeId);

    if (empty($mode) || empty($merchantAccount)) {
        return false;
    }

    if (empty($testApiKey) && empty($liveApiKey)) {
        return false;
    }

    $liveConnectionData = getLiveData($storeId, $merchantAccount, $liveApiKey, $liveUrlPrefix);
    $testConnectionData = getTestData($storeId, $merchantAccount, $testApiKey);

    if (!$liveConnectionData && !$testConnectionData) {
        return false;
    }

    $connectionSettings = new ConnectionSettings(
        $storeId,
        'LIVE' === strtoupper($mode) ? Mode::MODE_LIVE : Mode::MODE_TEST,
        $testConnectionData,
        $liveConnectionData
    );

    return initializeConnection($storeId, $connectionSettings);
}

/**
 * @param string $storeId
 * @param ConnectionSettings $connectionSettings
 *
 * @return bool
 *
 * @throws EmptyConnectionDataException
 * @throws EmptyStoreException
 * @throws InvalidModeException
 */
function initializeConnection(string $storeId, ConnectionSettings $connectionSettings): bool
{
    try {
        getConnectionSettingsRepository()->setConnectionSettings($connectionSettings);
        getConnectionService()->saveConnectionData($connectionSettings);
    } catch (Exception $e) {
        Logger::logWarning(
            'Migration of connection settings failed for store ' . $storeId
            . ' because ' . $e->getMessage()
        );

        if ($connectionSettings->getMode() === Mode::MODE_LIVE) {
            $settings = new ConnectionSettings(
                $connectionSettings->getStoreId(),
                $connectionSettings->getMode(),
                null,
                new ConnectionData(
                    $connectionSettings->getLiveData()->getApiKey(),
                    '',
                    $connectionSettings->getLiveData()->getClientPrefix()
                )
            );
        } else {
            $settings = new ConnectionSettings(
                $connectionSettings->getStoreId(),
                $connectionSettings->getMode(),
                new ConnectionData($connectionSettings->getTestData()->getApiKey(), ''),
                null
            );
        }

        getConnectionSettingsRepository()->setConnectionSettings($settings);

        return false;
    }

    return true;
}

/**
 * @param string $storeId
 * @param string $merchantAccount
 * @param $testApiKey
 *
 * @return ConnectionData|null
 *
 * @throws ConnectionSettingsNotFoundException
 * @throws EmptyConnectionDataException
 * @throws EmptyStoreException
 * @throws InvalidModeException
 */
function getTestData(string $storeId, string $merchantAccount, $testApiKey): ?ConnectionData
{
    if (empty($testApiKey)) {
        return null;
    }

    $testApiCredentials = getApiCredentialsFor(
        new ConnectionSettings(
            $storeId,
            Mode::MODE_TEST,
            new ConnectionData($testApiKey, $merchantAccount),
            null
        )
    );

    if (!$testApiCredentials) {
        return null;
    }

    return new ConnectionData(
        $testApiKey,
        $merchantAccount,
        '',
        '',
        $testApiCredentials
    );
}

/**
 * @param string $shopId
 * @param string $merchantAccount
 * @param string $liveApiKey
 * @param string $liveUrlPrefix
 *
 * @return ConnectionData|null
 *
 * @throws ConnectionSettingsNotFoundException
 * @throws EmptyConnectionDataException
 * @throws EmptyStoreException
 * @throws InvalidModeException
 */
function getLiveData(
    string $shopId,
    string $merchantAccount,
    string $liveApiKey,
    string $liveUrlPrefix
): ?ConnectionData {
    if (empty($liveApiKey) || empty($liveUrlPrefix)) {
        return null;
    }

    $liveApiCredentials = getApiCredentialsFor(
        new ConnectionSettings(
            $shopId,
            Mode::MODE_LIVE,
            null,
            new ConnectionData($liveApiKey, $merchantAccount, $liveUrlPrefix)
        )
    );

    if (!$liveApiCredentials) {
        return null;
    }

    return new ConnectionData(
        $liveApiKey,
        $merchantAccount,
        $liveUrlPrefix,
        '',
        $liveApiCredentials
    );
}

/**
 * @param ConnectionSettings $connectionSettings
 *
 * @return ApiCredentials|null
 *
 * @throws ConnectionSettingsNotFoundException
 */
function getApiCredentialsFor(ConnectionSettings $connectionSettings): ?ApiCredentials
{
    $apiCredentials = getProxy($connectionSettings)->getApiCredentialDetails();

    if (!$apiCredentials || !$apiCredentials->isActive()) {
        return null;
    }

    return $apiCredentials;
}


function removeHooks(AdyenOfficial $module)
{
    $registeredHooks = [
        'displayPaymentTop',
        'actionFrontControllerSetMedia',
        'paymentOptions',
        'paymentReturn',
        'actionOrderSlipAdd',
        'actionEmailSendBefore'
    ];

    foreach ($registeredHooks as $hook) {
        $module->unregisterHook($hook);
    }
}

function removeObsoleteData()
{
    /** @noinspection SqlDialectInspection */
    \Db::getInstance()->delete("configuration", "name LIKE '%ADYEN%'");
}

/**
 * Removes obsolete files.
 *
 * @param AdyenOfficial $module
 *
 * @return void
 */
function removeObsoleteFiles(AdyenOfficial $module)
{
    Logger::logDebug('Removing obsolete files');
    $installPath = $module->getLocalPath();
    removeDirectories($installPath);
    removeFiles($installPath);
}

/**
 * Removes obsolete directories.
 *
 * @param string $installPath
 *
 * @return void
 */
function removeDirectories(string $installPath)
{
    $directories = [
        'application',
        'exception',
        'helper',
        'infra',
        'model',
        'override/classes',
        'service',
        'tools',
        'views/img',
        'views/js/payment-components'
    ];

    foreach ($directories as $directory) {
        Tools::deleteDirectory($installPath . $directory);
    }
}

/**
 * Removes obsolete files.
 *
 * @param string $installPath
 *
 * @return void
 */
function removeFiles(string $installPath)
{
    $files = [
        'controllers/admin/AdminAdyenOfficialPrestashopController.php',
        'controllers/admin/AdminAdyenOfficialPrestashopCronController.php',
        'controllers/admin/AdminAdyenOfficialPrestashopLogFetcherController.php',
        'controllers/admin/AdminAdyenOfficialPrestashopValidatorController.php',
        'controllers/front/Notifications.php',
        'controllers/front/Payment.php',
        'controllers/front/PaymentsDetails.php',
        'controllers/front/Result.php',
        'controllers/FrontController.php',
        'upgrade/upgrade-1.0.1.php',
        'upgrade/upgrade-1.1.0.php',
        'upgrade/upgrade-1.2.0.php',
        'upgrade/upgrade-1.3.0.php',
        'upgrade/upgrade-2.0.0.php',
        'upgrade/upgrade-2.1.3.php',
        'upgrade/upgrade-3.3.0.php',
        'upgrade/upgrade-3.4.0.php',
        'upgrade/upgrade-3.6.0.php',
        'upgrade/upgrade-3.7.0.php',
        'upgrade/upgrade-3.7.1.php',
        'views/css/adyen.css',
        'views/css/adyen-admin.css',
        'views/css/adyen_components.css',
        'views/js/adyen-admin.js',
        'views/js/bundle.js',
        'views/js/checkout-component-renderer.js',
        'views/js/polyfill.js',
        'views/templates/admin/log-fetcher.tpl',
        'views/templates/admin/validator.tpl',
        'views/templates/email/waiting_for_payment_adyen.tpl',
        'views/templates/email/waiting_for_payment_adyen.txt',
        'views/templates/front/adyencheckout.tpl',
        'views/templates/front/error.tpl',
        'views/templates/front/get-started.tpl',
        'views/templates/front/order-confirmation.tpl',
        'views/templates/front/payment-method.tpl',
        'views/templates/front/stored-payment-method.tpl',
    ];

    foreach ($files as $file) {
        Tools::deleteFile($installPath . $file);
    }
}

//<editor-fold desc="Service getters" defaultstate="collapsed">

/**
 * @param ConnectionSettings $connectionSettings
 *
 * @return Proxy
 *
 * @throws ConnectionSettingsNotFoundException
 */
function getProxy(ConnectionSettings $connectionSettings): Proxy
{
    return ProxyFactory::makeProxy(Proxy::class, $connectionSettings);
}

/**
 * @return ConnectionSettingsRepository
 */
function getConnectionSettingsRepository(): ConnectionSettingsRepository
{
    return ServiceRegister::getService(ConnectionSettingsRepository::class);
}

/**
 * @return ConnectionService
 */
function getConnectionService(): ConnectionService
{
    return ServiceRegister::getService(ConnectionService::class);
}

/**
 * @return QueueService
 */
function getQueueService(): QueueService
{
    return ServiceRegister::getService(QueueService::class);
}

/**
 * @return TaskRunnerManager
 */
function getTaskRunnerManager(): TaskRunnerManager
{
    return ServiceRegister::getService(TaskRunnerManager::CLASS_NAME);
}

//</editor-fold>