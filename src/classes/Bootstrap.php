<?php

namespace AdyenPayment\Classes;

use Adyen\Core\BusinessLogic\Bootstrap\SingleInstance;
use Adyen\Core\BusinessLogic\BootstrapComponent;
use Adyen\Core\BusinessLogic\DataAccess\AdyenGiving\Entities\DonationsData;
use Adyen\Core\BusinessLogic\DataAccess\AdyenGivingSettings\Entities\AdyenGivingSettings;
use Adyen\Core\BusinessLogic\DataAccess\Connection\Entities\ConnectionSettings;
use Adyen\Core\BusinessLogic\DataAccess\Disconnect\Entities\DisconnectTime;
use Adyen\Core\BusinessLogic\DataAccess\GeneralSettings\Entities\GeneralSettings;
use Adyen\Core\BusinessLogic\DataAccess\Notifications\Entities\Notification;
use Adyen\Core\BusinessLogic\DataAccess\OrderSettings\Entities\OrderStatusMapping;
use Adyen\Core\BusinessLogic\DataAccess\Payment\Entities\PaymentMethod;
use Adyen\Core\BusinessLogic\DataAccess\TransactionHistory\Entities\TransactionHistory;
use Adyen\Core\BusinessLogic\DataAccess\TransactionLog\Entities\TransactionLog;
use Adyen\Core\BusinessLogic\DataAccess\Webhook\Entities\WebhookConfig;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService as OrderServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Payment\ShopPaymentService as ShopPaymentServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\AddressProcessor as AddressProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\BasketItemsProcessor as BasketItemsProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\BirthdayProcessor as BirthdayProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\DeviceFingerprintProcessor as DeviceFingerprintProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\L2L3DataProcessor as L2L3DataProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\LineItemsProcessor as LineItemsProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperEmailProcessor as ShopperEmailProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperLocaleProcessor as ShopperLocaleProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperNameProcessor as ShopperNameProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperReferenceProcessor as ShopperReferenceProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ApplicationInfoProcessor as ApplicationInfoProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\AddressProcessor as PaymentLinkAddressProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ApplicationInfoProcessor as PaymentLinkApplicationInfoProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\LineItemsProcessor as PaymentLinkLineItemsProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperBirthdayProcessor as PaymentLinkShopperBirthdayProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperEmailProcessor as PaymentLinkShopperEmailProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperLocaleProcessor as PaymentLinkShopperLocaleProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperNameProcessor as PaymentLinkShopperNameProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperReferenceProcessor as PaymentLinkShopperReferenceProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Store\StoreService as StoreServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\SystemInfo\SystemInfoService as SystemInfoServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Version\VersionService as VersionServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Webhook\WebhookUrlService as WebhookUrlServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Payment\Services\PaymentService;
use Adyen\Core\BusinessLogic\Domain\Payment\Repositories\PaymentMethodConfigRepository;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Repositories\TransactionHistoryRepository;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionHistoryService;
use Adyen\Core\BusinessLogic\Domain\Webhook\Repositories\WebhookConfigRepository;
use Adyen\Core\BusinessLogic\Domain\Webhook\Services\OrderStatusProvider;
use Adyen\Core\BusinessLogic\Domain\Webhook\Services\WebhookSynchronizationService as CoreWebhookSynchronizationService;
use Adyen\Core\Infrastructure\Configuration\ConfigEntity;
use Adyen\Core\Infrastructure\Configuration\Configuration;
use Adyen\Core\Infrastructure\Http\CurlHttpClient;
use Adyen\Core\Infrastructure\Http\HttpClient;
use Adyen\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Adyen\Core\Infrastructure\Logger\LogData;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ORM\RepositoryRegistry;
use Adyen\Core\Infrastructure\Serializer\Concrete\JsonSerializer;
use Adyen\Core\Infrastructure\Serializer\Serializer;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Core\Infrastructure\TaskExecution\Process;
use Adyen\Core\Infrastructure\TaskExecution\QueueItem;
use AdyenPayment\Classes\Repositories\AdyenGivingRepository;
use AdyenPayment\Classes\Repositories\BaseRepository;
use AdyenPayment\Classes\Repositories\BaseRepositoryWithConditionalDelete;
use AdyenPayment\Classes\Repositories\ConfigurationRepository;
use AdyenPayment\Classes\Repositories\Integration\ConnectionSettingsRepository;
use AdyenPayment\Classes\Repositories\LogsRepository;
use AdyenPayment\Classes\Repositories\OrderRepository;
use AdyenPayment\Classes\Repositories\PaymentMethodRepository;
use AdyenPayment\Classes\Repositories\QueueItemRepository;
use AdyenPayment\Classes\Repositories\TransactionLogRepository;
use AdyenPayment\Classes\Services\Domain\CreditCardsService;
use AdyenPayment\Classes\Services\Domain\WebhookSynchronizationService;
use AdyenPayment\Classes\Services\Integration\ConfigService;
use AdyenPayment\Classes\Services\Integration\Logger\LoggerService;
use AdyenPayment\Classes\Services\Integration\OrderService;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\AddressProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\ApplicationInfoProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\BasketItemsProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\BirthdayProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\DeviceFingerprintProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\L2L3DataProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\LineItemsProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\ShopperEmailProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\ShopperLocaleProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\ShopperNameProcessor;
use AdyenPayment\Classes\Services\Integration\PaymentProcessors\ShopperReferenceProcessor;
use AdyenPayment\Classes\Services\Integration\ShopPaymentService;
use AdyenPayment\Classes\Services\Integration\StoreService;
use AdyenPayment\Classes\Services\Integration\SystemInfoService;
use AdyenPayment\Classes\Services\Integration\VersionInfoService;
use AdyenPayment\Classes\Services\Integration\WebhookUrlService;
use Adyen\Core\BusinessLogic\Domain\Connection\Repositories\ConnectionSettingsRepository as ConnectionSettingsRepositoryInterface;
use Adyen\Core\BusinessLogic\Domain\Stores\Services\StoreService as DomainStoreServiceCore;
use AdyenPayment\Classes\Services\Domain\StoreService as DomainStoreServiceIntegration;
use AdyenPayment\Classes\Services\LogsService;
use AdyenPayment\Classes\Repositories\NotificationsRepository;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
use AdyenPayment\Classes\Version\Version175;
use AdyenPayment\Classes\Version\Version177;

/**
 * Class Bootstrap
 *
 * @package AdyenPayment
 */
class Bootstrap extends BootstrapComponent
{
    /**
     * @return void
     *
     * @throws RepositoryClassException
     */
    public static function init(): void
    {
        parent::init();

        self::initServices();
        self::initPaymentRequestProcessors();
        self::initRepositories();
    }

    /**
     * @inerhitDoc
     */
    protected static function initServices(): void
    {
        parent::initServices();

        ServiceRegister::registerService(
            Serializer::CLASS_NAME,
            static function () {
                return new JsonSerializer();
            }
        );

        ServiceRegister::registerService(
            HttpClient::CLASS_NAME,
            static function () {
                return new CurlHttpClient();
            }
        );

        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            static function () {
                return ConfigService::getInstance();
            }
        );

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            static function () {
                return new LoggerService();
            }
        );

        ServiceRegister::registerService(
            OrderServiceInterface::class,
            static function () {
                return new OrderService(
                    ServiceRegister::getService(TransactionHistoryRepository::class),
                    ServiceRegister::getService(VersionHandler::class)
                );
            }
        );

        ServiceRegister::registerService(
            ShopPaymentServiceInterface::class,
            static function () {
                return new ShopPaymentService(ServiceRegister::getService(PaymentMethodConfigRepository::class));
            }
        );

        ServiceRegister::registerService(
            StoreServiceInterface::class,
            static function () {
                return new StoreService(
                    new ConfigurationRepository(),
                    RepositoryRegistry::getRepository(ConnectionSettings::getClassName())
                );
            }
        );

        ServiceRegister::registerService(
            SystemInfoServiceInterface::class,
            static function () {
                return new SystemInfoService(
                    ConfigService::getInstance()
                );
            }
        );

        ServiceRegister::registerService(
            VersionServiceInterface::class,
            static function () {
                return new VersionInfoService();
            }
        );

        ServiceRegister::registerService(
            WebhookUrlServiceInterface::class,
            static function () {
                return new WebhookUrlService();
            }
        );

        ServiceRegister::registerService(
            DomainStoreServiceCore::class,
            static function () {
                return new DomainStoreServiceIntegration(
                    ServiceRegister::getService(StoreServiceInterface::class),
                    ServiceRegister::getService(ConnectionSettingsRepositoryInterface::class)
                );
            }
        );

        ServiceRegister::registerService(
            LogsService::class,
            static function () {
                return new LogsService(new LogsRepository());
            }
        );

        ServiceRegister::registerService(
            DomainStoreServiceCore::class,
            static function () {
                return new DomainStoreServiceIntegration(
                    ServiceRegister::getService(StoreServiceInterface::class),
                    ServiceRegister::getService(ConnectionSettingsRepositoryInterface::class)
                );
            }
        );

        ServiceRegister::registerService(
            CoreWebhookSynchronizationService::class,
            static function () {
                return new WebhookSynchronizationService(
                    ServiceRegister::getService(TransactionHistoryService::class),
                    ServiceRegister::getService(OrderServiceInterface::class),
                    ServiceRegister::getService(OrderStatusProvider::class)
                );
            }
        );

        ServiceRegister::registerService(
            VersionHandler::class,
            static function () {
                if (version_compare(_PS_VERSION_, '1.7.7.0', '<')) {
                    return new Version175();
                }

                return new Version177();
            }
        );

        ServiceRegister::registerService(
            CreditCardsService::class,
            static function () {
                return new CreditCardsService(RepositoryRegistry::getRepository(PaymentMethod::getClassName()));
            }
        );
    }

    /**
     * @inerhitDoc
     */
    protected static function initPaymentRequestProcessors(): void
    {
        parent::initPaymentRequestProcessors();

        ServiceRegister::registerService(
            AddressProcessorInterface::class,
            static function () {
                return new AddressProcessor();
            }
        );

        ServiceRegister::registerService(
            BasketItemsProcessorInterface::class,
            static function () {
                return new BasketItemsProcessor(
                    ServiceRegister::getService(GeneralSettingsService::class)
                );
            }
        );

        ServiceRegister::registerService(
            BirthdayProcessorInterface::class,
            static function () {
                return new BirthdayProcessor();
            }
        );

        ServiceRegister::registerService(
            DeviceFingerprintProcessorInterface::class,
            static function () {
                return new DeviceFingerprintProcessor();
            }
        );

        ServiceRegister::registerService(
            L2L3DataProcessorInterface::class,
            static function () {
                return new L2L3DataProcessor(
                    ServiceRegister::getService(PaymentService::class)
                );
            }
        );

        ServiceRegister::registerService(
            LineItemsProcessorInterface::class,
            static function () {
                return new LineItemsProcessor();
            }
        );

        ServiceRegister::registerService(
            ShopperEmailProcessorInterface::class,
            static function () {
                return new ShopperEmailProcessor();
            }
        );

        ServiceRegister::registerService(
            ShopperLocaleProcessorInterface::class,
            static function () {
                return new ShopperLocaleProcessor();
            }
        );

        ServiceRegister::registerService(
            ShopperNameProcessorInterface::class,
            static function () {
                return new ShopperNameProcessor();
            }
        );

        ServiceRegister::registerService(
            ShopperReferenceProcessorInterface::class,
            static function () {
                return new ShopperReferenceProcessor();
            }
        );

        ServiceRegister::registerService(
            ApplicationInfoProcessorInterface::class,
            static function () {
                return new ApplicationInfoProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkAddressProcessorInterface::class,
            static function () {
                return new AddressProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkApplicationInfoProcessorInterface::class,
            static function () {
                return new ApplicationInfoProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkLineItemsProcessorInterface::class,
            static function () {
                return new LineItemsProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkShopperBirthdayProcessorInterface::class,
            static function () {
                return new BirthdayProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkShopperEmailProcessorInterface::class,
            static function () {
                return new ShopperEmailProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkShopperLocaleProcessorInterface::class,
            static function () {
                return new ShopperLocaleProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkShopperNameProcessorInterface::class,
            static function () {
                return new ShopperNameProcessor();
            }
        );

        ServiceRegister::registerService(
            PaymentLinkShopperReferenceProcessorInterface::class,
            static function () {
                return new ShopperReferenceProcessor();
            }
        );
    }

    /**
     * @inerhitDoc
     *
     * @throws RepositoryClassException
     */
    protected static function initRepositories(): void
    {
        parent::initRepositories();

        ServiceRegister::registerService(
            ConnectionSettingsRepositoryInterface::class,
            new SingleInstance(static function () {
                return new ConnectionSettingsRepository(
                    RepositoryRegistry::getRepository(ConnectionSettings::getClassName()),
                    ServiceRegister::getService(StoreContext::class)
                );
            })
        );

        ServiceRegister::registerService(
            WebhookConfigRepository::class,
            new SingleInstance(static function () {
                return new Repositories\Integration\WebhookConfigRepository(
                    RepositoryRegistry::getRepository(WebhookConfig::getClassName()),
                    ServiceRegister::getService(StoreContext::class)
                );
            })
        );

        ServiceRegister::registerService(
            OrderRepository::class,
            function () {
                return new OrderRepository();
            }
        );

        RepositoryRegistry::registerRepository(Process::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ConfigEntity::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(LogData::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(AdyenGivingSettings::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(GeneralSettings::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(WebhookConfig::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(OrderStatusMapping::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(DisconnectTime::getClassName(), BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(QueueItem::getClassName(), QueueItemRepository::getClassName());
        RepositoryRegistry::registerRepository(
            ConnectionSettings::getClassName(),
            BaseRepositoryWithConditionalDelete::getClassName()
        );
        RepositoryRegistry::registerRepository(PaymentMethod::getClassName(), PaymentMethodRepository::getClassName());
        RepositoryRegistry::registerRepository(
            TransactionHistory::getClassName(),
            TransactionLogRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            TransactionLog::getClassName(),
            TransactionLogRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(Notification::getClassName(), NotificationsRepository::getClassName());
        RepositoryRegistry::registerRepository(DonationsData::getClassName(), AdyenGivingRepository::getClassName());
    }
}
