<?php

namespace Adyen\PrestaShop\service\builder;

class Customer
{
    /**
     * Builds the customer related data
     *
     * @param string $email
     * @param string $phoneNumber
     * @param string $gender
     * @param string $dateOfBirth
     * @param string $firstName
     * @param string $lastName
     * @param string $countryCode
     * @param string $localeCode
     * @param string $shopperIp
     * @param int $customerId
     * @param array $request
     *
     * @return array
     */
    public function buildCustomerData(
        $email = '',
        $phoneNumber = '',
        $gender = '',
        $dateOfBirth = '',
        $firstName = '',
        $lastName = '',
        $countryCode = '',
        $localeCode = '',
        $shopperIp = '',
        $customerId = 0,
        $request = []
    ) {
        // Add shopperReference to identify the unique shoppers in the store by id, necessary for recurring payments
        if (!empty($customerId)) {
            $request['shopperReference'] = str_pad($customerId, 3, '0', STR_PAD_LEFT);
        }

        if (!empty($email) && empty($request['shopperEmail'])) {
            $request['shopperEmail'] = $email;
        }

        if (!empty($phoneNumber) && empty($request['telephoneNumber'])) {
            $request['telephoneNumber'] = $phoneNumber;
        }

        if (!empty($gender) && empty($request['shopperName']['gender'])) {
            $request['shopperName']['gender'] = $gender;
        }

        if (!empty($dateOfBirth) && empty($request['dateOfBirth'])) {
            $request['dateOfBirth'] = $dateOfBirth;
        }

        if (!empty($firstName) && empty($request['shopperName']['firstName'])) {
            $request['shopperName']['firstName'] = $firstName;
        }

        if (!empty($lastName) && empty($request['shopperName']['lastName'])) {
            $request['shopperName']['lastName'] = $lastName;
        }

        if (!empty($countryCode)) {
            $request['countryCode'] = $countryCode;
        }

        if (!empty($localeCode)) {
            $request['shopperLocale'] = $localeCode;
        }

        if (!empty($shopperIp)) {
            $request['shopperIP'] = $shopperIp;
        }

        return $request;
    }
}
