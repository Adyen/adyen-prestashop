<?php

use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\SessionService;

/**
 * Class AdyenOfficialStateDataGetModuleFrontController
 */
class AdyenOfficialStateDataGetModuleFrontController extends ModuleFrontController
{
    /** @var string File name for translation contextualization */
    public const FILE_NAME = 'AdyenOfficialStateDataGetModuleFrontController';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    public function postProcess()
    {
        $token = Tools::getValue('token');
        $expectedToken = Tools::getToken(false);

        if (strcasecmp($expectedToken, $token) !== 0) {
            AdyenPrestaShopUtility::die403(['message' => 'Invalid token.']);
        }

        $key = Tools::getValue('key');

        $result = SessionService::get($key, false);

        if (!$result) {
            AdyenPrestaShopUtility::dieJsonArray([]);
        }

        AdyenPrestaShopUtility::dieJsonArray([$key => $result]);
    }
}
