<?php

namespace AdyenPayment\Classes\Services\Integration\Logger;

use Adyen\Core\Infrastructure\Configuration\Configuration;
use Adyen\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Adyen\Core\Infrastructure\Logger\LogData;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ServiceRegister;

/**
 * Class LoggerService
 */
class LoggerService implements ShopLoggerAdapter
{
    /**
     * PrestaShop log severity level codes.
     */
    private const PRESTASHOP_INFO = 1;
    private const PRESTASHOP_WARNING = 2;
    private const PRESTASHOP_ERROR = 3;

    /**
     * Log level names for corresponding log level codes.
     *
     * @var array
     */
    private static $logLevelName = [
        Logger::ERROR => 'ERROR',
        Logger::WARNING => 'WARNING',
        Logger::INFO => 'INFO',
        Logger::DEBUG => 'DEBUG',
    ];

    /**
     * Mappings of Adyen log severity levels to PrestaShop log severity levels.
     *
     * @var array
     */
    private static $logMapping = [
        Logger::ERROR => self::PRESTASHOP_ERROR,
        Logger::WARNING => self::PRESTASHOP_WARNING,
        Logger::INFO => self::PRESTASHOP_INFO,
        Logger::DEBUG => self::PRESTASHOP_INFO,
    ];

    /**
     * {@inheritDoc}
     */
    public function logMessage(LogData $data): void
    {
        /** @var Configuration $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $minLogLevel = $configService->getMinLogLevel();
        $logLevel = $data->getLogLevel();

        if (($logLevel > $minLogLevel) && !$configService->isDebugModeEnabled()) {
            return;
        }

        $message = 'ADYEN LOG: | '
            . 'Date: ' . date('d/m/Y') . ' | '
            . 'Time: ' . date('H:i:s') . ' | '
            . 'Log level: ' . self::$logLevelName[$logLevel] . ' | '
            . 'Message: ' . $data->getMessage();
        $context = $data->getContext();
        if (!empty($context)) {
            $contextData = [];
            foreach ($context as $item) {
                $contextData[$item->getName()] = print_r($item->getValue(), true);
            }

            $message .= ' | Content data: [' . json_encode($contextData) . ']';
        }

        \PrestaShopLogger::addLog($message, self::$logMapping[$logLevel]);
    }
}
