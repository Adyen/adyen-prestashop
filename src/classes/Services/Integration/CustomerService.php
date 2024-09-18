<?php

namespace AdyenPayment\Classes\Services\Integration;

use Address;
use AdyenPayment\Classes\Repositories\CartProductRepository;
use AdyenPayment\Classes\Repositories\CountryRepository;
use AdyenPayment\Classes\Services\CheckoutHandler;
use Configuration;
use Country;
use Customer;
use Cart;
use Exception;
use PrestaShop\Module\PrestashopCheckout\Exception\PsCheckoutException;
use PrestaShop\Module\PrestashopCheckout\Updater\CustomerUpdater;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShopDatabaseException;
use State;
use stdClass;

/**
 * Class CustomerService.
 *
 * @package AdyenPayment\Classes\Services\Integration
 */
class CustomerService
{
    /**
     * Handle creation and customer login
     *
     * @param string $email
     * @param array $data
     *
     * @return Customer
     *
     */
    public function createAndLoginCustomer(string $email, $data): Customer
    {
        $email = str_replace(['"', "'"], '', $email);

        /** @var int $customerId */
        $customers = Customer::getCustomersByEmail($email);

        if (empty($customers)) {
            $billingAddress = json_decode($data['adyenBillingAddress']);

            if ($billingAddress->lastName === '') {
                $fullName = explode(' ', $billingAddress->firstName);
                $firstName = $fullName[0];
                $lastName = $fullName[1] ?? $fullName[0];
            } else {
                $firstName = $billingAddress->firstName;
                $lastName = $billingAddress->lastName;
            }

            $customer = $this->createGuestCustomer(
                $email,
                $firstName,
                $lastName
            );
        } else {
            $lastCustomer = end($customers);
            $customer = new Customer($lastCustomer['id_customer']);
        }

        if (method_exists(\Context::getContext(), 'updateCustomer')) {
            \Context::getContext()->updateCustomer($customer);
        }

        return $customer;
    }

    /**
     * Sets customer's billing and shipping address.
     *
     * @param Customer $customer
     * @param array $data
     * @param Cart $cart
     *
     * @return Cart
     *
     * @throws CountryNotFoundException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function setCustomerAddresses($customer, $data, $cart)
    {
        list($shippingAddressId, $billingAddressId) = $this->saveAddresses($customer, $data);

        if (method_exists(\Context::getContext(), 'updateCustomer')) {
            \Context::getContext()->updateCustomer($customer);
        }

        \Context::getContext()->cart->id_address_invoice = $billingAddressId;
        \Context::getContext()->cart->id_address_delivery = $shippingAddressId;
        \Context::getContext()->cart->update();
        \Context::getContext()->cart->id_carrier = CheckoutHandler::getCarrierId($cart);
        $this->updateDeliveryAddress($cart->id, $shippingAddressId);
        \Context::getContext()->cart->update();

        return $cart;
    }

    public function saveAddresses($customer, $data)
    {
        $billingAddress = json_decode($data['adyenBillingAddress']);
        $shippingAddress = json_decode($data['adyenShippingAddress']);

        $shippingAddress = $this->createAddress($shippingAddress);
        $shippingAddress->id_customer = $customer->id;
        $shippingAddress->add();

        $billingAddress = $this->createAddress($billingAddress);
        $billingAddress->id_customer = $customer->id;
        $billingAddress->add();

        return [
            $shippingAddress->id,
            $billingAddress->id,
        ];
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
    public function createAddress(stdClass $sourceAddress): Address
    {
        $address = new Address();
        $countryId = Country::getByIso($sourceAddress->country);
        $stateId = State::getIdByIso($sourceAddress->state);

        if (!$countryId) {
            throw new CountryNotFoundException('Country not supported');
        }

        if (empty($sourceAddress->lastName)) {
            $fullName = explode(' ', $sourceAddress->firstName);
            $firstName = $fullName[0];
            $lastName = $fullName[1] ?? $fullName[0];
        } else {
            $firstName = $sourceAddress->firstName;
            $lastName = $sourceAddress->lastName;
        }

        $address->lastname = $lastName;
        $address->firstname = $firstName;
        $address->address1 = $sourceAddress->street;
        $address->id_country = $countryId;
        $address->id_state = $stateId;
        $address->city = $sourceAddress->city;
        $address->alias = 'Home';
        $address->postcode = $sourceAddress->zipCode;

        return $address;
    }

    /**
     * @param $countryIso
     * @param $langId
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    public function verifyIfCountryNotRestricted($countryIso, $langId): bool
    {
        $activeCountries = Country::getCountries($langId, true);
        $activeCountryCodes = array_column($activeCountries, 'iso_code');

        $moduleActiveCountries = $this->getCountryRepository()->getModuleCountries(
            (int)\Module::getInstanceByName('adyenofficial')->id,
            (int)\Context::getContext()->shop->id
        );
        $moduleActiveCountryCodes = array_column($moduleActiveCountries, 'iso_code');

        return in_array($countryIso, $activeCountryCodes, true) &&
            in_array($countryIso, $moduleActiveCountryCodes, true);
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
     * @throws PsCheckoutException|\PrestaShopException
     */
    private function createGuestCustomer(string $email, string $firstName, string $lastName): Customer
    {
        $customer = new Customer();
        $customer->email = $email;
        $customer->firstname = $firstName;
        $customer->lastname = $lastName;
        $customer->is_guest = true;
        $customer->id_default_group = (int)Configuration::get('PS_GUEST_GROUP');

        if (class_exists('PrestaShop\PrestaShop\Core\Crypto\Hashing')) {
            $crypto = new \PrestaShop\PrestaShop\Core\Crypto\Hashing();
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
     * @param int $cartId
     * @param int $addressId
     *
     * @return void
     */
    public function updateDeliveryAddress(int $cartId, int $addressId): void
    {
        $this->getCartProductRepository()->updateDeliveryAddress($cartId, $addressId);
    }

    /**
     * @return CountryRepository
     */
    private function getCountryRepository(): CountryRepository
    {
        return new CountryRepository();
    }

    /**
     * @return CartProductRepository
     */
    private function getCartProductRepository(): CartProductRepository
    {
        return new CartProductRepository();
    }
}
