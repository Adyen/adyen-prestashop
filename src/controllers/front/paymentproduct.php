<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PaymentRequest\Request\StartTransactionRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\Utility\SessionService;
use AdyenPayment\Classes\Utility\Url;
use AdyenPayment\Controllers\PaymentController;
use Currency as PrestaCurrency;
use PrestaShop\Module\PrestashopCheckout\Exception\PsCheckoutException;
use PrestaShop\Module\PrestashopCheckout\Updater\CustomerUpdater;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;

/**
 * Class AdyenOfficialPaymentProductModuleFrontController
 */
class AdyenOfficialPaymentProductModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPaymentProductModuleFrontController';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:adyenofficial/views/templates/front/adyen-additional-details.tpl');
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function postProcess()
    {
        $currencyId = (int)$this->context->currency->id;
        $langId = (int)$this->context->language->id;
        $cart = $this->createEmptyCart($currencyId, $langId);
        $customerId = (int)$this->context->customer->id;
        $data = Tools::getAllValues();
        $customerEmail = str_replace(['"', "'"], '', $data['adyenEmail']);
        if ($customerId) {
            $customer = new Customer($customerId);
        } elseif ($customerEmail) {
            $customer = $this->createAndLoginCustomer($customerEmail, $data);
        }

        $addresses = $customer->getAddresses($langId);
        if (count($addresses) === 0) {
            $this->handleNotSuccessfulPayment(self::FILE_NAME);
        } else {
            $cart = $this->updateCart($customer, $addresses[0]['id_address'], $addresses[0]['id_address'], $cart);
        }

        $product = json_decode($data['product'], true);
        $this->addProductToCart($cart, $product);

        $additionalData = !empty($data['adyen-additional-data']) ? json_decode(
            $data['adyen-additional-data'],
            true
        ) : [];
        $type = !empty($additionalData['paymentMethod']['type']) ? $additionalData['paymentMethod']['type'] : '';
        $currency = new PrestaCurrency($currencyId);
        try {
            $response = CheckoutApi::get()->paymentRequest((string)$cart->id_shop)->startTransaction(
                new StartTransactionRequest(
                    $type,
                    Amount::fromFloat(
                        $this->getOrderTotal($cart, $type),
                        Currency::fromIsoCode($currency->iso_code ?? 'EUR')
                    ),
                    (string)$cart->id,
                    Url::getFrontUrl('paymentredirect', ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => $type]
                    ),
                    $additionalData,
                    [],
                    $this->getShopperReferenceFromCart($cart)
                )
            );

            if (!$response->isSuccessful()) {
                $product = new \Product($product['id_product'] ?? 0);
                $this->handleNotSuccessfulPayment(self::FILE_NAME, $product->getLink());
            }

            if (!$response->isAdditionalActionRequired()) {
                $this->handleSuccessfulPaymentWithoutAdditionalData($type, $cart);
            }

            $this->handleSuccessfulPaymentWithAdditionalData($response, $type, $cart);
        } catch (Throwable $e) {
            Logger::logError(
                'Adyen failed to create order from Cart with ID: ' . $cart->id . ' Reason: ' . $e->getMessage()
            );
            $message = $this->module->l('Your payment could not be processed, please resubmit order.', self::FILE_NAME);
            $this->errors[] = $message;
            $this->redirectWithNotifications(Context::getContext()->link->getPageLink('order'));
        }
    }

    /**
     * @param string $fileName
     * @param int $productId
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function redirectBack(string $fileName, int $productId): void
    {
        $message = $this->module->l('Your payment could not be processed, please resubmit order.', $fileName);
        SessionService::set(
            'errorMessage',
            $message
        );
        $product = new \Product($productId);

        if ($this->isAjaxRequest()) {
            die(
            json_encode(
                [
                    'nextStepUrl' => $product->getLink()
                ]
            )
            );
        }

        Tools::redirect($product->getLink());
    }


    /**
     * @param Cart $cart
     * @param $product
     *
     * @return void
     */
    private function addProductToCart(Cart $cart, $product)
    {
        $productId = (int)$product['id_product'];
        $idProductAttribute = (int)$product['id_product_attribute'];
        $quantityWanted = (int)$product['quantity_wanted'];
        $customizationId = (int)$product['id_customization'];

        $cart->updateQty($quantityWanted, $productId, $idProductAttribute, $customizationId);
    }

    /**
     * @param int $currencyId
     * @param int $langId
     *
     * @return Cart
     *
     * @throws PrestaShopException
     */
    private function createEmptyCart(int $currencyId, int $langId): Cart
    {
        $cart = new Cart();
        $cart->id_currency = $currencyId;
        $cart->id_lang = $langId;
        $cart->id_shop = Context::getContext()->shop->id;
        $cart->id_carrier = CheckoutHandler::getCarrierId($cart);
        $cart->save();

        return $cart;
    }

    /**
     * @param Customer $customer
     * @param int $deliveryAddressId
     * @param int $invoiceAddressId
     * @param Cart $cart
     *
     * @return Cart
     *
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

    /**
     * @param Cart $cart
     *
     * @return ShopperReference|null
     */
    private function getShopperReferenceFromCart(Cart $cart): ?ShopperReference
    {
        $customer = new Customer($cart->id_customer);

        if (!$customer) {
            return null;
        }

        $shop = Shop::getShop(Context::getContext()->shop->id);

        return ShopperReference::parse($shop['domain'] . '_' . Context::getContext()->shop->id . '_' . $customer->id);
    }

    /**
     * Handle creation and customer login
     *
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     *
     * @return Customer
     *
     * @throws PsCheckoutException|CountryNotFoundException
     */
    private function createAndLoginCustomer(string $email, $data): Customer
    {
        /** @var int $customerId */
        $customerId = Customer::customerExists($email, true);

        if ($customerId === 0) {
            $billingAddress = json_decode($data['adyenBillingAddress']);
            $shippingAddress = json_decode($data['adyenShippingAddress']);

            if (empty($billingAddress->lastname)) {

                $fullName = explode(' ', $billingAddress->firstName);
                $firstName = $fullName[0];
                $lastName = $fullName[1];
            } else {
                $firstName = $billingAddress->firstName;
                $lastName = $billingAddress->lastName;
            }

            $customer = $this->createGuestCustomer(
                $email,
                $firstName,
                $lastName
            );

            $shippingAddress = $this->createAddress($shippingAddress);
            $shippingAddress->id_customer = $customer->id;
            $shippingAddress->add();

            $billingAddress = $this->createAddress($billingAddress);
            $billingAddress->id_customer = $customer->id;
            $billingAddress->add();
        } else {
            $customer = new Customer($customerId);
        }

        if (method_exists($this->context, 'updateCustomer')) {
            $this->context->updateCustomer($customer);
        } else {
            CustomerUpdater::updateContextCustomer($this->context, $customer);
        }

        return $customer;
    }

    /**
     * Create a guest customer.
     *
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     *
     * @return Customer
     *
     * @throws PsCheckoutException
     */
    private function createGuestCustomer(string $email, string $firstName, string $lastName): Customer
    {
        $customer = new Customer();
        $customer->email = $email;
        $customer->firstname = $firstName;
        $customer->lastname = $lastName;
        $customer->is_guest = true;
        $customer->id_default_group = (int) Configuration::get('PS_GUEST_GROUP');

        if (class_exists('PrestaShop\PrestaShop\Core\Crypto\Hashing')) {
            $crypto = new PrestaShop\PrestaShop\Core\Crypto\Hashing();
            $customer->passwd = $crypto->hash(
                time() . _COOKIE_KEY_,
                _COOKIE_KEY_
            );
        } else {
            $customer->passwd = md5(time() . _COOKIE_KEY_);
        }

        try {
            $customer->save();
        } catch (Exception $exception) {
            throw new PsCheckoutException($exception->getMessage(), PsCheckoutException::PSCHECKOUT_EXPRESS_CHECKOUT_CANNOT_SAVE_CUSTOMER, $exception);
        }

        return $customer;
    }

    /**
     * Creates a PrestaShop address entity based on the source address.
     *
     * @param stdClass $sourceAddress
     *
     * @return Address
     *
     * @throws CountryNotFoundException
     */
    private function createAddress(stdClass $sourceAddress): Address
    {
        $address = new Address();
        $countryId = Country::getByIso($sourceAddress->country);

        if (!$countryId) {
            throw new CountryNotFoundException('Country not supported');
        }

        if (empty($sourceAddress->lastname)) {
            $fullName = explode(' ', $sourceAddress->firstName);
            $firstName = $fullName[0];
            $lastName = $fullName[1];
        } else {
            $firstName = $sourceAddress->firstName;
            $lastName = $sourceAddress->lastName;
        }

        $address->lastname = $lastName;
        $address->firstname = $firstName;
        $address->address1 = $sourceAddress->street;
        $address->id_country = $countryId;
        $address->city = $sourceAddress->city;
        $address->alias = 'Home';
        $address->postcode = $sourceAddress->zipCode;
        $address->phone = $sourceAddress->phone;
        $address->phone_mobile = $sourceAddress->phone;

        return $address;
    }
}
