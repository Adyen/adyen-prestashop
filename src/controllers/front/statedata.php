<?php

use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\SessionService;

/**
 * Class AdyenOfficialStateDataModuleFrontController
 */
class AdyenOfficialStateDataModuleFrontController extends ModuleFrontController
{
    /** @var string File name for translation contextualization */
    public const FILE_NAME = 'AdyenOfficialStateDataModuleFrontController';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function postProcess(): void
    {
        $token = Tools::getValue('token');
        $expectedToken = Tools::getToken(false);

        if (strcasecmp($expectedToken, $token) !== 0) {
            AdyenPrestaShopUtility::die403(['message' => 'Invalid token.']);
        }

        $payload = Tools::file_get_contents('php://input');
        $data = json_decode($payload, true);

        foreach ($data as $key => $item) {
            SessionService::set($key, json_decode($item, true));
        }

        AdyenPrestaShopUtility::dieJsonArray([]);
    }
}
