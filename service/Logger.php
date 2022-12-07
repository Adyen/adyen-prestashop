<?php

namespace Adyen\PrestaShop\service;

use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\exception\CommandException;
use Monolog\Handler\StreamHandler;

class Logger extends \Monolog\Logger
{
    public const NAME = 'ADYEN';
    public const ADYEN_API = 201;
    public const ADYEN_RESULT = 202;
    public const ADYEN_NOTIFICATION = 203;
    public const ADYEN_CRONJOB = 204;

    private static $adyenHandlers = [
        self::DEBUG => [
            'level' => self::DEBUG,
            'fileName' => 'debug.log',
        ],
        self::INFO => [
            'level' => self::INFO,
            'fileName' => 'info.log',
        ],
        self::ADYEN_API => [
            'level' => self::ADYEN_API,
            'fileName' => 'adyen_api.log',
        ],
        self::ADYEN_RESULT => [
            'level' => self::ADYEN_RESULT,
            'fileName' => 'adyen_result.log',
        ],
        self::ADYEN_NOTIFICATION => [
            'level' => self::ADYEN_NOTIFICATION,
            'fileName' => 'adyen_notification.log',
        ],
        self::ADYEN_CRONJOB => [
            'level' => self::ADYEN_CRONJOB,
            'fileName' => 'adyen_cronjob.log',
        ],
        self::NOTICE => [
            'level' => self::NOTICE,
            'fileName' => 'notice.log',
        ],
        self::WARNING => [
            'level' => self::WARNING,
            'fileName' => 'warning.log',
        ],
        self::ERROR => [
            'level' => self::ERROR,
            'fileName' => 'error.log',
        ],
    ];

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     * Overrule the default to add Adyen specific loggers to log into separate files
     *
     * @var array Logging levels
     */
    protected static $levels = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::ADYEN_API => 'ADYEN_API',
        self::ADYEN_RESULT => 'ADYEN_RESULT',
        self::ADYEN_NOTIFICATION => 'ADYEN_NOTIFICATION',
        self::ADYEN_CRONJOB => 'ADYEN_CRONJOB',
        self::NOTICE => 'NOTICE',
        self::WARNING => 'WARNING',
        self::ERROR => 'ERROR',
        self::CRITICAL => 'CRITICAL',
        self::ALERT => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    /**
     * Levels which should also be logged via PrestaShop, mapped to their PrestaShop severity
     *
     * @var array
     */
    protected static $prestashopLoggable = [
        self::EMERGENCY => 4,
        self::CRITICAL => 4,
        self::ERROR => 3,
        self::WARNING => 2,
    ];

    /**
     * @var VersionChecker
     */
    private $versionChecker;

    /**
     * Logger constructor.
     *
     * @param VersionChecker $versionChecker
     *
     * @throws \Exception
     */
    public function __construct(
        VersionChecker $versionChecker
    ) {
        parent::__construct(self::NAME);
        $this->versionChecker = $versionChecker;
        $this->registerAdyenLogHandlers();
    }

    /**
     * Retrieve default log path depending on the PrestaShop version
     *
     * @return string
     */
    private function getLogPath()
    {
        if ($this->versionChecker->isPrestaShop16()) {
            $path = _PS_ROOT_DIR_ . '/log';
        } else {
            $path = _PS_ROOT_DIR_ . '/var/logs';
        }

        return $path;
    }

    /**
     * Retrieve Adyen log path
     * If it doesn't exist yet then also creates it
     *
     * @return string
     *
     * @throws CommandException
     */
    private function getAdyenLogPath()
    {
        $path = $this->getLogPath() . '/adyen';

        if (!file_exists($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new CommandException('Creating the Adyen log folder failed');
            }
        }

        return $path;
    }

    /**
     * @param string $message
     * @param array $context
     *
     * @return bool
     */
    public function addAdyenAPI($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_API, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     *
     * @return bool
     */
    public function addAdyenResult($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_RESULT, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     *
     * @return bool
     */
    public function addAdyenNotification($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_NOTIFICATION, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     *
     * @return bool
     */
    public function addAdyenCronjob($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_CRONJOB, $message, $context);
    }

    /**
     * Adds a log record and depending on the level, also add it to the prestashop logs
     *
     * @param int $level The logging level
     * @param string $message The log message
     * @param array $context The log context
     * @param bool $callPrestaShopLogger Set to false to disable logging with the PrestaShop default logger as well
     *
     * @return bool Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [], $callPrestaShopLogger = true)
    {
        $context['is_exception'] = $message instanceof \Exception;
        if (array_key_exists($level, self::$prestashopLoggable) && $callPrestaShopLogger) {
            \PrestaShopLogger::addLog(
                $message,
                self::$prestashopLoggable[$level],
                null,
                null,
                null,
                true
            );
        }

        return parent::addRecord($level, $message, $context);
    }

    /**
     * @throws CommandException
     */
    private function registerAdyenLogHandlers()
    {
        $adyenLogPath = $this->getAdyenLogPath();

        foreach (self::$adyenHandlers as $adyenHandler) {
            $this->pushHandler(new StreamHandler(
                $adyenLogPath . '/' . $adyenHandler['fileName'],
                $adyenHandler['level'],
                false
            ));
        }
    }
}
