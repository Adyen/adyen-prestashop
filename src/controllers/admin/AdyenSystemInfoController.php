<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Services\LogsService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\ZipGenerator;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenSystemInfoController
 */
class AdyenSystemInfoController extends AdyenBaseController
{
    private const SYSTEM_INFO_FILE_NAME = 'adyen-debug-data.zip';

    private const FILES_NAMES = [
        'PHP_INFO_FILE_NAME' => 'phpinfo.html',
        'CONFIGURED_PAYMENT_METHODS' => 'adyen-payment-methods.json',
        'SYSTEM_INFO' => 'system-info.json',
        'AUTO_TEST' => 'auto-test.json',
        'CONNECTION_SETTINGS' => 'connection-settings.json',
        'WEBHOOK_VALIDATION' => 'webhook-validation.json',
        'LOGS' => 'logs.json'
    ];

    /**
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     * @throws Exception
     */
    public function displayAjaxSystemInfo(): void
    {
        $info = AdminAPI::get()->systemInfo()->getSystemInfo()->toArray();
        $autoTestReport = AdminAPI::get()->autoTest()->autoTestReport()->toArray();
        $logs = $this->getLogsService()->getLogs();
        $file = ZipGenerator::createZip($info, $autoTestReport, $logs, self::FILES_NAMES);

        AdyenPrestaShopUtility::dieFile($file, self::SYSTEM_INFO_FILE_NAME);
    }

    /**
     * @return mixed
     */
    private function getLogsService()
    {
        return ServiceRegister::getService(LogsService::class);
    }
}
