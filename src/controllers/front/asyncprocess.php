<?php

use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Core\Infrastructure\TaskExecution\AsyncProcessStarterService;
use Adyen\Core\Infrastructure\TaskExecution\Interfaces\AsyncProcessService;
use AdyenPayment\Classes\Bootstrap;

/**
 * Class AdyenOfficialAsyncProcessModuleFrontController
 */
class AdyenOfficialAsyncProcessModuleFrontController extends ModuleFrontController
{
    /**
     * AdyenOfficialAsyncProcessModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Starts process asynchronously.
     *
     * @return void
     */
    public function initContent(): void
    {
        $guid = trim(Tools::getValue('guid'));

        if ($guid !== 'auto-configure') {
            /** @var AsyncProcessStarterService $asyncProcessService */
            $asyncProcessService = ServiceRegister::getService(AsyncProcessService::CLASS_NAME);
            $asyncProcessService->runProcess($guid);
        }

        exit(json_encode(['success' => true]));
    }
}
