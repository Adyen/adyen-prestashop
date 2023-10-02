<?php

use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialApplePayModuleFrontController
 */
class AdyenOfficialApplePayModuleFrontController extends ModuleFrontController
{
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
     */
    public function initContent(): void
    {
        parent::initContent();

        fpassthru(
            fopen(
                'https://eu.adyen.link/.well-known/apple-developer-merchantid-domain-association',
                'rb'
            )
        );

        AdyenPrestaShopUtility::diePlain();
    }
}
