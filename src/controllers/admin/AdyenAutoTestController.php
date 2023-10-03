<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\Infrastructure\Exceptions\StorageNotAccessibleException;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Adyen\Core\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenAutoTestController
 */
class AdyenAutoTestController extends AdyenBaseController
{
    private const FILE_NAME = 'auto-test-logs.json';

    /**
     * @return void
     *
     * @throws StorageNotAccessibleException
     * @throws QueueStorageUnavailableException
     */
    public function displayAjaxStartAutoTest(): void
    {
        $result = AdminAPI::get()->autoTest()->startAutoTest();

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryClassException
     * @throws RepositoryNotRegisteredException
     */
    public function displayAjaxAutoTestStatus(): void
    {
        $queueItemId = Tools::getValue('queueItemId');

        $result = AdminAPI::get()->autoTest()->autoTestStatus($queueItemId ?? 0);

        AdyenPrestaShopUtility::dieJson($result);
    }

    /**
     * @return void
     *
     * @throws RepositoryNotRegisteredException
     */
    public function displayAjaxGetReport(): void
    {
        $result = AdminAPI::get()->autoTest()->autoTestReport()->toArray();
        $fileName = tempnam(sys_get_temp_dir(), 'adyen_auto_test_info');
        $out = fopen($fileName, 'w');
        fwrite($out, json_encode($result));
        fclose($out);

        AdyenPrestaShopUtility::dieFile($fileName, self::FILE_NAME);
    }
}
