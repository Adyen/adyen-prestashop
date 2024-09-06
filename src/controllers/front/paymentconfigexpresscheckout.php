<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutConfig\Response\PaymentCheckoutConfigResponse;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\Services\Integration\CustomerService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;

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
     * @throws CountryNotFoundException
     */
    public function postProcess()
    {
        $newAddress = Tools::getValue('newAddress');
        if ($newAddress) {
            $config = $this->getConfigForNewAddress($newAddress);

            header('Content-Type: application/json');
            die(json_encode(['amount' => $config->toArray()['amount']['value']]));
        }

        $cartId = (int)Tools::getValue('cartId');
        if ($cartId !== 0) {
            $cart = new Cart($cartId);
            if (!$cart->id) {
                AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
            }

            $config = CheckoutHandler::getExpressCheckoutConfig($cart);
        } else {
            $cart = $this->getCartForProduct();
            $config = CheckoutHandler::getExpressCheckoutConfig($cart);
            $cart->delete();
        }

        AdyenPrestaShopUtility::dieJson($config);
    }

    /**
     * @param array $data
     *
     * @return PaymentCheckoutConfigResponse
     *
     * @throws CountryNotFoundException
     * @throws InvalidCurrencyCode
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getConfigForNewAddress($data)
    {
        $cartId = (int)Tools::getValue('cartId');
        $customerId = (int)$this->context->customer->id;

        if ($cartId !== 0) {
            $cart = new Cart($cartId);
            if (!$cart->id) {
                AdyenPrestaShopUtility::die400(['message' => 'Invalid parameters.']);
            }

            $cart = $this->updateCartWithCustomerAndAddresses($data, $cart);
            $config = CheckoutHandler::getExpressCheckoutConfig($cart);

            $address = new Address($cart->id_address_delivery);
            $address->delete();

            return $config;
        }

        $cart = $this->addProductsToCart();
        $cart = $this->updateCartWithCustomerAndAddresses($data, $cart);
        $config = CheckoutHandler::getExpressCheckoutConfig($cart);

        $address = new Address($cart->id_address_delivery);
        $address->delete();
        if ($customerId === 0) {
            $customer = new Customer($address->id_customer);
            $customer->delete();
        }

        $cart->delete();

        return $config;
    }

    /**
     * @param array $data
     * @param Cart $cart
     *
     * @return Cart
     *
     * @throws CountryNotFoundException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function updateCartWithCustomerAndAddresses($data, $cart): Cart
    {
        $customerId = (int)$this->context->customer->id;
        $langId = (int)$this->context->language->id;
        /** @var CustomerService $customerService */
        $customerService = ServiceRegister::getService(CustomerService::class);

        if ($customerId === 0) {
            $customer = $customerService->createAndLoginCustomer('guest@test.com', $data);
        } else {
            $customer = new Customer($customerId);
        }

        $customerService->setCustomerAddresses($customer, $data);
        $addresses = $customer->getAddresses($langId);
        $lastAddress = end($addresses);

        return $this->updateCart($customer, $lastAddress['id_address'], $lastAddress['id_address'], $cart);
    }

    /**
     * @return Cart
     *
     * @throws PrestaShopException
     */
    private function addProductsToCart(): Cart
    {
        $currencyId = (int)$this->context->currency->id;
        $langId = (int)$this->context->language->id;
        $cart = $this->createEmptyCart($currencyId, $langId);
        $productId = (int)Tools::getValue('id_product');
        $productAttributeId = (int)Tools::getValue('id_product_attribute');
        $quantityWanted = (int)Tools::getValue('quantity_wanted');
        $customizationId = (int)Tools::getValue('id_customization');
        $cart->updateQty($quantityWanted, $productId, $productAttributeId, $customizationId);

        return $cart;
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
