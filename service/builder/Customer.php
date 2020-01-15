<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\builder;

class Customer extends Builder
{
    /**
     * Builds the customer related data
     *
     * @param $isOpenInvoicePaymentMethod
     * @param string $email
     * @param string $phoneNumber
     * @param string $firstName
     * @param string $lastName
     * @param string $countryCode
     * @param string $localeCode
     * @param int $customerId
     * @param array $request
     * @return array|mixed
     */
    public function buildCustomerData(
        $isOpenInvoicePaymentMethod,
        $email = '',
        $phoneNumber = '',
        $firstName = '',
        $lastName = '',
        $countryCode = '',
        $localeCode = '',
        $customerId = 0,
        $request = array()
    ) {
        // Add shopperReference to identify the unique shoppers in the store by id, necessary for recurring payments
        if (!empty($customerId)) {
            $request['shopperReference'] = $customerId;
        }

        // Open invoice methods requires different request format
        if ($isOpenInvoicePaymentMethod) {
            $request = $this->buildCustomerDataForOpenInvoicePaymentMethod($email, $phoneNumber, $firstName, $lastName,
                $request);
        } else {
            $request = $this->buildCustomerDataForNonOpenInvoicePaymentMethod($email, $phoneNumber, $firstName,
                $lastName, $request);
        }

        if (!empty($countryCode)) {
            $request['countryCode'] = $countryCode;
        }

        if (!empty($localeCode)) {
            $request['shopperLocale'] = $localeCode;
        }

        return $request;
    }

    /**
     * Builds customer related data listed in the parameter list for open invoice payment methods
     *
     * @param string $email
     * @param string $phoneNumber
     * @param string $firstName
     * @param string $lastName
     * @param array $request
     * @return array
     */
    private function buildCustomerDataForOpenInvoicePaymentMethod(
        $email,
        $phoneNumber,
        $firstName,
        $lastName,
        $request = array()
    ) {
        if (!empty($email)) {
            $request['paymentMethod']['personalDetails']['shopperEmail'] = $email;
        }

        if (!empty($phoneNumber)) {
            $request['paymentMethod']['personalDetails']['telephoneNumber'] = $phoneNumber;
        }

        if (!empty($firstName)) {
            $request['paymentMethod']['personalDetails']['firstName'] = $firstName;
        }

        if (!empty($lastName)) {
            $request['paymentMethod']['personalDetails']['lastName'] = $lastName;
        }
        return $request;
    }

    /**
     * Builds customer related data listed in the parameter list for not open invoice payment methods
     *
     * @param string $email
     * @param string $phoneNumber
     * @param string $firstName
     * @param string $lastName
     * @param array $request
     * @return array
     */
    private function buildCustomerDataForNonOpenInvoicePaymentMethod(
        $email,
        $phoneNumber,
        $firstName,
        $lastName,
        $request = array()
    ) {
        if (!empty($email)) {
            $request['shopperEmail'] = $email;
        }

        if (!empty($phoneNumber)) {
            $request['telephoneNumber'] = $phoneNumber;
        }

        if (!empty($firstName)) {
            $request['shopperName']['firstName'] = $firstName;
        }

        if (!empty($lastName)) {
            $request['shopperName']['lastName'] = $lastName;
        }

        return $request;
    }
}
