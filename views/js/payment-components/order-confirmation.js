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

    var paymentActionContainer = $('[data-adyen-payment-action-container]');

    // container doesn't exist, something went wrong on the template side
    if (!paymentActionContainer.length) {
        return;
    }

    var paymentAction = paymentActionContainer.data().adyenPaymentAction.action;

    window.adyenCheckout
        .createFromAction(paymentAction)
        .mount('[data-adyen-payment-action-container]');
});

