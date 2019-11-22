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

    var containers = $('[data-local-payment-method]');
    containers.each(function (index, element) {
        element = $(element);
        var localPaymentMethodSpecifics = element.data();
        var configuration = {
            'onChange': function (state) {
                if (state.isValid) {
                    element.find('[name="adyen-payment-issuer"]').val(state.data.paymentMethod.issuer);
                }
            }
        };
        if (localPaymentMethodSpecifics.issuerList && localPaymentMethodSpecifics.issuerList.length) {
            configuration.items = localPaymentMethodSpecifics.issuerList;
        }
        adyenCheckout
            .create(localPaymentMethodSpecifics.localPaymentMethod, configuration)
            .mount(element.find('[data-adyen-payment-container]').get(0));
    });
});

