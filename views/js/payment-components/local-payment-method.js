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
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

jQuery(function ($) {
    if (!window.adyenCheckout) {
        return;
    }

    var placeOrderAllowed = false;
    var data;

    // use this object to iterate through the stored payment methods
    var paymentMethods = window.adyenCheckout.paymentMethodsResponse.paymentMethods;

    // Iterate through the payment methods list we got from the adyen checkout component
    paymentMethods.forEach(function (paymentMethod) {
        //  if the container doesn't exits don't try to render the component
        var paymentMethodContainer = $('[data-local-payment-method="' + paymentMethod.type + '"]');

        // container doesn't exist, something went wrong on the template side
        if (!paymentMethodContainer.length) {
            return;
        }

        /* Subscribes to the adyen payment method form submission */
        var paymentForm = $(".adyen-payment-form-" + paymentMethod.type);

        // Use data to reteive the payment method data
        paymentForm.on('submit', function (e) {
            e.preventDefault();

            if (!placeOrderAllowed && paymentMethod.details) {
                return;
            }

            var paymentMethodData = {'type': paymentMethod.type};
            var browserInfo = {};
            if (!!data) {
                paymentMethodData = data.paymentMethod;
                browserInfo = data.browserInfo;
            }

            processPayment({
                'isAjax': true,
                'browserInfo': browserInfo,
                'paymentMethod': paymentMethodData
            }, paymentForm);
        });

        // If payment method doesn't have details, just skip it
        if (!paymentMethod.details) {
            return;
        }

        // filter personal details extra fields from component and only leave the necessary ones
        paymentMethod.details = filterOutOpenInvoiceComponentDetails(paymentMethod.details);

        /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
        var configuration = Object.assign(paymentMethod, {
            'onChange': function (state) {
                if (state.isValid) {
                    data = state.data;
                    placeOrderAllowed = true;
                } else {
                    placeOrderAllowed = false;
                    resetFields();
                }
            }
        });

        try {
            adyenCheckout
                .create(paymentMethod.type, configuration)
                .mount(paymentMethodContainer.find('[data-adyen-payment-container]').get(0));
        } catch (err) {
            // The component does not exist yet
        }


    });

    /**
     * Does the initial payments call with the encrypted data from the card component
     */
    function processPayment(data, paymentForm) {
        var paymentProcessUrl = paymentForm.attr('action');

        $.ajax({
            type: "POST",
            url: paymentProcessUrl,
            data: data,
            dataType: "json",
            success: function (response) {
                processControllerResponse(response, paymentForm);
            },
            error: function (response) {
                paymentForm.find('.error-container').text(response.message).fadeIn(1000);
            }
        });
    }

    /**
     * Reset card details
     */
    function resetFields() {
        data = "";
    }

    /**
     * Decides what to do next based on the payments response
     */
    function processControllerResponse(response, paymentForm) {
        switch (response.action) {
            case 'error':
                // show error message
                paymentForm.find('.error-container').text(response.message).fadeIn(1000);
                break;
            case 'redirect':
                window.location.replace(response.redirectUrl);
                break;
            default:
                // show error message
                console.log("Something went wrong on the frontend");
        }
    }

    /**
     * In the open invoice components we need to validate only the personal details and only the
     * dateOfBirth, telephoneNumber and gender if it's set in the admin
     * @param details
     * @returns {Array}
     */
    function filterOutOpenInvoiceComponentDetails(details) {
        var filteredDetails = details.map(function (parentDetail) {
            // filter only personalDetails, billingAddress, separateDeliveryAddress, deliveryAddress and consentCheckbox
            if ("personalDetails" !== parentDetail.key &&
                "billingAddress" !== parentDetail.key &&
                "separateDeliveryAddress" !== parentDetail.key &&
                "deliveryAddress" !== parentDetail.key &&
                "consentCheckbox" !== parentDetail.key
            ) {
                return parentDetail;
            }

            if ("personalDetails" === parentDetail.key) {
                var detailObject = parentDetail.details.map(function (detail) {
                    if ('dateOfBirth' === detail.key ||
                        'telephoneNumber' === detail.key ||
                        'gender' === detail.key) {
                        return detail;
                    }
                });

                if (!!detailObject) {
                    return {
                        "key": parentDetail.key,
                        "type": parentDetail.type,
                        "details": filterUndefinedItemsInArray(detailObject)
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
});
