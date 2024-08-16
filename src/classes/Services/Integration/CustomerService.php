<?php

namespace AdyenPayment\Classes\Services\Integration;

use Address;
use Configuration;
use Country;
use Customer;
use PrestaShop\Module\PrestashopCheckout\Exception\PsCheckoutException;
use PrestaShop\Module\PrestashopCheckout\Updater\CustomerUpdater;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
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
     * @throws PsCheckoutException|CountryNotFoundException
     * @throws \PrestaShopException
     */
    public function createAndLoginCustomer(string $email, $data): Customer
    {
        $email = str_replace(['"', "'"], '', $email);

        /** @var int $customerId */
        $customers = Customer::getCustomersByEmail($email);

        if (empty($customers)) {
            $billingAddress = json_decode($data['adyenBillingAddress']);
            $shippingAddress = json_decode($data['adyenShippingAddress']);

            if ($billingAddress->lastname === '') {
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
            $lastCustomer = end($customers);
            $customer = new Customer($lastCustomer['id_customer']);
        }

        if (method_exists(\Context::getContext(), 'updateCustomer')) {
            \Context::getContext()->updateCustomer($customer);
            \Context::getContext()->cart->id_customer = $customer->id;
        } else {
            CustomerUpdater::updateContextCustomer(\Context::getContext(), $customer);
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
     * @throws PsCheckoutException|\PrestaShopException
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
