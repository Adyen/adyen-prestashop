<?php

namespace AdyenPayment\Classes\Utility;

use ZipArchive;

/**
 * Class ZipGenerator
 *
 * @package AdyenPayment\Classes\Utility
 */
class ZipGenerator
{
    /**
     * Creates system info zip.
     *
     * @param array $info
     * @param array $autoTestReport
     * @param string $logs
     * @param array $filesNames
     *
     * @return false|string
     */
    public static function createZip(array $info, array $autoTestReport, string $logs, array $filesNames)
    {
        $file = tempnam(sys_get_temp_dir(), 'adyen_system_info');

        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);

        $zip->addFromString($filesNames['PHP_INFO_FILE_NAME'], $info['phpInfo']);
        $zip->addFromString(
            $filesNames['CONFIGURED_PAYMENT_METHODS'],
            json_encode($info['paymentMethods'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString(
            $filesNames['SYSTEM_INFO'],
            json_encode($info['systemInfo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString(
            $filesNames['CONNECTION_SETTINGS'],
            json_encode($info['connectionSettings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString($filesNames['WEBHOOK_VALIDATION'], $info['webhookValidation']);
        $zip->addFromString(
            $filesNames['AUTO_TEST'],
            json_encode($autoTestReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString(
            $filesNames['QUEUE'],
            json_encode($info['queueItems'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString(
            $filesNames['TRANSACTION_LOGS'],
            json_encode($info['transactionLogs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString($filesNames['LOGS'], $logs);

        $zip->close();

        return $file;
    }
}
