/*
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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

/**
 * In the open invoice components we need to validate only the personal details and only the
 * dateOfBirth, telephoneNumber and gender if it's set in the admin
 * @param details
 * @returns {Array}
 */
function filterOutOpenInvoiceComponentDetails(details) {
    var filteredDetails = details.map(function (parentDetail) {
        if ("personalDetails" === parentDetail.key) {
            var detailObject = parentDetail.details.map(function (detail) {
                if ('dateOfBirth' === detail.key  ||
                    'telephoneNumber' === detail.key  ||
                    'gender' === detail.key) {
                    return detail;
                }
            });

            if (!!detailObject) {
                return {
                    "key": parentDetail.key,
                    "type": parentDetail.type,
                    "details": self.filterUndefinedItemsInArray(detailObject)
                };
            }
        }
    });

    return filterUndefinedItemsInArray(filteredDetails);
}

/**
 * Helper function to filter out the undefined items from an array
 * @param arr
 * @returns {*}
 */
function filterUndefinedItemsInArray(arr) {
    return arr.filter(function (item) {
        return typeof item !== 'undefined';
    });
}

jQuery(function ($) {
    if (!window.adyenCheckout) {
        return;
    }

    // use this object to iterate through the stored payment methods
    var paymentMethods = window.adyenCheckout.paymentMethodsResponse.paymentMethods;

    // Iterate through the payment methods list we got from the adyen checkout component
    paymentMethods.forEach(function (paymentMethod) {
        // If payment method doesn't have details, just skip it
        if (!paymentMethod.details) {
            return;
        }

        //  if the container doesn't exits don't try to render the component
        var paymentMethodContainer = $('[data-local-payment-method="' + paymentMethod.type + '"]');

        // container doesn't exist, something went wrong on the template side
        if (!paymentMethodContainer.length) {
            return;
        }

        // filter personal details extra fields from component and only leave the necessary ones
        paymentMethod.details = filterOutOpenInvoiceComponentDetails(paymentMethod.details);

        /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
        var configuration = Object.assign(paymentMethod, {
            'onChange': function (state) {
                if (state.isValid) {
                    paymentMethodContainer.find('[name="adyen-payment-issuer"]').val(state.data.paymentMethod.issuer);
                }
            }
        });

        adyenCheckout
            .create(paymentMethod.type, configuration)
            .mount(paymentMethodContainer.find('[data-adyen-payment-container]').get(0));

    });
});

