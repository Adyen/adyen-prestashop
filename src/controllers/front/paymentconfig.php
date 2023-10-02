<?php

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenPaymentModuleFrontController
 */
class AdyenOfficialPaymentConfigModuleFrontController extends ModuleFrontController
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
     *
     * @throws InvalidCurrencyCode
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $cartId = (int)Tools::getValue('cartId');
        $cart = new Cart($cartId > 0 ? $cartId : Context::getContext()->cart->id);
        $config = CheckoutHandler::getPaymentCheckoutConfig($cart);

        AdyenPrestaShopUtility::dieJson($config);
    }
}
