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

        /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
        var configuration = mergeObjects([paymentMethod, {
            'onChange': function (state) {
                if (state.isValid) {
                    paymentMethodContainer.find('[name="adyen-payment-issuer"]').val(state.data.paymentMethod.issuer);
                }
            }
        }]);

        adyenCheckout
            .create(paymentMethod.type, configuration)
            .mount(paymentMethodContainer.find('[data-adyen-payment-container]').get(0));

    });
});

