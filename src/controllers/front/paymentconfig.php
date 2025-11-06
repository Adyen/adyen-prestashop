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
        $cartId = $this->getCartId();
        $discountAmount = (int)Tools::getValue('discountAmount');
        $cart = new Cart($cartId > 0 ? $cartId : Context::getContext()->cart->id);
        $customer = new Customer(Context::getContext()->customer->id);

        if (!$cart->id || !(int)$customer->id) {
            AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
        }

        if ((int)$customer->id !== (int)$cart->id_customer) {
            AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
        }

        $config = CheckoutHandler::getPaymentCheckoutConfig($cart, $discountAmount);

        AdyenPrestaShopUtility::dieJson($config);
    }

    private function getCartId(): int
    {
        if ($this->context->cart && (int)$this->context->cart->id > 0) {
            return (int)$this->context->cart->id;
        }

        $cartId = (int)Tools::getValue('id_cart');

        if ($cartId <= 0) {
            AdyenPrestaShopUtility::die400(['message' => 'Missing or invalid cart ID.']);
        }

        $orderId = (int)Tools::getValue('id_order');
        $key = (string)Tools::getValue('key');

        if ($orderId > 0 && $key) {
            $order = Order::getByCartId($cartId);

            if ($order->id !== $orderId) {
                AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
            }

            if (!hash_equals($order->secure_key, $key)) {
                AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
            }
        }

        return $cartId;
    }
}
