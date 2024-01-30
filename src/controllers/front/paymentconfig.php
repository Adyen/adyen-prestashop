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
        $customer = new Customer(Context::getContext()->customer->id);

        if (!$cart->id || !(int)$customer->id) {
            AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
        }

        if ((int)$customer->id !== (int)$cart->id_customer) {
            AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
        }

        $config = CheckoutHandler::getPaymentCheckoutConfig($cart);

        AdyenPrestaShopUtility::dieJson($config);
    }
}
