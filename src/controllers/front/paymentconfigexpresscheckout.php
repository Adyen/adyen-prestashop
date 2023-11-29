<?php

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialPaymentConfigExpressCheckoutModuleFrontController
 */
class AdyenOfficialPaymentConfigExpressCheckoutModuleFrontController extends ModuleFrontController
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
        if ($cartId !== 0) {
            $cart = new Cart($cartId);
            $config = CheckoutHandler::getExpressCheckoutConfig($cart);
        } else {
            $cart = $this->getCartForProduct();
            $config = CheckoutHandler::getExpressCheckoutConfig($cart);
            $cart->delete();
        }
        $customer = new Customer(Context::getContext()->customer->id);
        if ((int)$customer->id !== (int)$cart->id_customer) {
            AdyenPrestaShopUtility::die400(
                ['message' => 'Cart with ID: ' . $cart->id . ' is not associated with customer' . ($customer->id ? ' with ID: ' . $customer->id : '. Customer is not logged in.')]
            );
        }

        AdyenPrestaShopUtility::dieJson($config);
    }

    /**
     * @return Cart
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCartForProduct(): Cart
    {
        $currencyId = (int)$this->context->currency->id;
        $langId = (int)$this->context->language->id;
        $cart = $this->createEmptyCart($currencyId, $langId);
        $productId = (int)Tools::getValue('id_product');
        $productAttributeId = (int)Tools::getValue('id_product_attribute');
        $quantityWanted = (int)Tools::getValue('quantity_wanted');
        $customizationId = (int)Tools::getValue('id_customization');
        $customerId = (int)$this->context->customer->id;
        $customer = new Customer($customerId);
        $addresses = $customer->getAddresses($langId);
        if (count($addresses) > 0) {
            $cart = $this->updateCart($customer, $addresses[0]['id_address'], $addresses[0]['id_address'], $cart);
        }

        $cart->updateQty($quantityWanted, $productId, $productAttributeId, $customizationId);

        return $cart;
    }

    /**
     * @param int $currencyId
     * @param int $langId
     * @return Cart
     * @throws PrestaShopException
     */
    private function createEmptyCart(int $currencyId, int $langId): Cart
    {
        $cart = new Cart();
        $cart->id_currency = $currencyId;
        $cart->id_lang = $langId;
        $cart->save();

        return $cart;
    }

    /**
     * @param Customer $customer
     * @param int $deliveryAddressId
     * @param int $invoiceAddressId
     * @param Cart $cart
     * @return Cart
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function updateCart(Customer $customer, int $deliveryAddressId, int $invoiceAddressId, Cart $cart): Cart
    {
        $cart->secure_key = $customer->secure_key;
        $cart->id_address_delivery = $deliveryAddressId;
        $cart->id_address_invoice = $invoiceAddressId;
        $cart->id_customer = $customer->id;
        $cart->update();

        return $cart;
    }
}
