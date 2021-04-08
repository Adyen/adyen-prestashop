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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

jQuery(document).ready(function() {
    if (!window.ADYEN_CHECKOUT_CONFIG) {
        return;
    }

    const componentButtonPaymentMethods = paymentMethodsWithPayButtonFromComponent;
    const prestaShopPlaceOrderButton = $('#payment-confirmation button');
    var placeOrderInProgress = false;

    renderPaymentMethods();

    // For prestashop 1.6 one page checkout retrieves the payment methods via
    // ajax when the terms and conditions checkbox is clicked, so to render the
    // components we call the renderPaymentMethods when the HOOK_PAYMENT childs
    // are being added or removed
    if (typeof IS_PRESTA_SHOP_16 !== 'undefined' && IS_PRESTA_SHOP_16) {
        // Select the node that will be observed for mutations
        const targetNode = document.getElementById('HOOK_PAYMENT');

        // In case the targetNode does not exist return early
        if (null === targetNode) {
            return;
        }

        // Options for the observer (which mutations to observe)
        const config = {attributes: true, childList: true, subtree: false};

        // Callback function to execute when mutations are observed
        const callback = function(mutationsList, observer) {
            // Use traditional 'for loops' for IE 11
            for (const mutation of mutationsList) {
                if (mutation.type === 'childList') {
                    // The children are being changed so disconnet the observer
                    // at first to avoid infinite loop
                    observer.disconnect();
                    // Render the adyen checkout components
                    renderPaymentMethods();
                }
            }

            // Connect the observer again in case the checkbox is clicked
            // multiple times
            observer.observe(targetNode, config);
        };

        // Create an observer instance linked to the callback function
        const observer = new MutationObserver(callback);

        // Start observing the target node for configured mutations
        try {
            observer.observe(targetNode, config);
        } catch (e) {
            // observer exception
        }
    } else {
        const queryParams = new URLSearchParams(window.location.search);
        if (queryParams.has('message')) {
            showRedirectErrorMessage(queryParams.get('message'));
        }

        $('input[name="payment-option"]').on('change', function(event) {

            let selectedPaymentForm = $(
                '#pay-with-' + event.target.id + '-form .adyen-payment');

            // Adyen payment method
            if (selectedPaymentForm.length > 0) {

                // not local payment method
                if (!('localPaymentMethod' in
                    selectedPaymentForm.get(0).dataset)) {

                    resetPrestaShopPlaceOrderButtonVisibility();
                    return;
                }

                let selectedAdyenPaymentMethodCode = selectedPaymentForm.get(
                    0).dataset.localPaymentMethod;

                if (!IS_PRESTA_SHOP_16) {
                    if (componentButtonPaymentMethods.includes(selectedAdyenPaymentMethodCode)) {
                        prestaShopPlaceOrderButton.hide();
                    } else {
                        prestaShopPlaceOrderButton.show();
                    }
                }
            } else {
                // In 1.7 in case the pay button is hidden and the customer selects a non adyen method
                resetPrestaShopPlaceOrderButtonVisibility();
            }
        });
    }

    function resetPrestaShopPlaceOrderButtonVisibility() {
        if (!IS_PRESTA_SHOP_16 && !prestaShopPlaceOrderButton.is(":visible")) {
            prestaShopPlaceOrderButton.show();
        }
    }

    function showRedirectErrorMessage(message) {
        const errorDiv = $('<div class="alert alert-danger error-container" role="alert"></div>');
        errorDiv.text(message);
        $('.payment-options').append(errorDiv);
    }

    function renderPaymentMethods() {
        var selectedPaymentMethod;
        var placeOrderAllowed = {};
        var popupModal;

        var skipComponents = ['giropay'];
        const handleActionComponents = ['paypal'];

        var componentBillingAddress = selectedInvoiceAddress;

        var countryCode = '';
        if ('country' in selectedInvoiceAddress) {
            countryCode = selectedInvoiceAddress.country;
        }

        var phoneNumber = '';
        var componentDeliveryAddress = {};
        var componentPersonalDetails;

        if (typeof prestashop !== 'undefined') {
            if (selectedInvoiceAddressId in prestashop.customer.addresses) {
                var invoiceAddress = prestashop.customer.addresses[selectedInvoiceAddressId];

                componentBillingAddress = {
                    city: invoiceAddress.city,
                    country: invoiceAddress.country_iso,
                    houseNumberOrName: invoiceAddress.address2,
                    postalCode: invoiceAddress.postcode,
                    street: invoiceAddress.address1,
                };

                countryCode = invoiceAddress.country_iso;
                phoneNumber = invoiceAddress.phone
                    ? invoiceAddress.phone
                    : invoiceAddress.phone_mobile;
            }

            if (selectedDeliveryAddressId in prestashop.customer.addresses) {
                var deliveryAddress = prestashop.customer.addresses[selectedDeliveryAddressId];

                componentDeliveryAddress = {
                    city: deliveryAddress.city,
                    country: deliveryAddress.country_iso,
                    houseNumberOrName: deliveryAddress.address2,
                    postalCode: deliveryAddress.postcode,
                    street: deliveryAddress.address1,
                };
            }

            componentPersonalDetails = {
                firstName: prestashop.customer.firstname,
                lastName: prestashop.customer.lastname,
                shopperEmail: prestashop.customer.email,
                telephoneNumber: phoneNumber,
                gender: getAdyenGenderByPrestashopType(
                    prestashop.customer.gender.type),
                dateOfBirth: prestashop.customer.birthday,
            };
        }

        const enableStoreDetails = !!isUserLoggedIn && !!enableStoredPaymentMethods;

        var configuration = Object.assign(
            ADYEN_CHECKOUT_CONFIG,
            {
                hasHolderName: true,
                holderNameRequired: false,
                enableStoreDetails: enableStoreDetails,
                countryCode: countryCode,
                data: {
                    billingAddress: componentBillingAddress,
                    deliveryAddress: componentDeliveryAddress,
                    personalDetails: componentPersonalDetails,
                },
                onAdditionalDetails: handleOnAdditionalDetails,
            },
        );

        window.adyenCheckout = new AdyenCheckout(configuration);

        // use this object to iterate through the payment methods
        var paymentMethods = window.adyenCheckout.paymentMethodsResponse.paymentMethods;

        // Iterate through the payment methods list we got from the adyen checkout component
        paymentMethods.forEach(function(paymentMethod) {
            //  if the container doesn't exits don't try to render the component
            var paymentMethodContainer = $(
                '[data-local-payment-method="' + paymentMethod.type + '"]');

            // container doesn't exist, something went wrong on the template side
            if (!paymentMethodContainer.length) {
                return;
            }

            /* Subscribes to the adyen payment method form submission */
            var paymentForm = $('.adyen-payment-form-' + paymentMethod.type);

            var component = renderPaymentComponent(paymentMethod,
                paymentMethodContainer, paymentForm);

            // Use data to retrieve the payment method data
            subscribeToPaymentFormSubmit(paymentForm, paymentMethod, component);
        });

        var checkoutStoredPaymentMethods = window.adyenCheckout.paymentMethodsResponse.storedPaymentMethods;

        // Iterate through the stored payment methods list we got from the adyen checkout component
        checkoutStoredPaymentMethods.forEach(function(storedPaymentMethod) {

            //  storedPaymentMethod.id = $storedPaymentApiId in the stored-payment-method.tpl
            //  don't try to render the component if the container doesn't exist
            var paymentMethodContainer = $(
                '[data-stored-payment-api-id="' + storedPaymentMethod.id +
                '"]');

            // container doesn't exist, something went wrong on the template side
            if (!paymentMethodContainer.length) {
                return;
            }

            var component = renderPaymentComponent(storedPaymentMethod,
                paymentMethodContainer);

            /* Subscribes to the adyen payment method form submission */
            var paymentForm = $(
                '.adyen-payment-form-' + storedPaymentMethod.id);

            subscribeToPaymentFormSubmit(paymentForm, storedPaymentMethod,
                component);
        });

        function renderPaymentComponent(
            paymentMethod, paymentMethodContainer, paymentForm) {

            if (skipComponents.includes(paymentMethod.type)) {
                return;
            }

            const containerDOM = paymentMethodContainer.find(
                '[data-adyen-payment-container]').get(0);

            let prestaShopPlaceOrderButtonDisabledObserver;
            if (!IS_PRESTA_SHOP_16) {
                prestaShopPlaceOrderButtonDisabledObserver = new MutationObserver(
                    function(mutations) {
                        mutations.forEach(function(mutation) {
                            // Check the modified attributeName is "disabled"
                            if (mutation.attributeName === 'disabled') {
                                // Hide info message if main button is not disabled
                                if (!prestaShopPlaceOrderButton.prop('disabled')) {
                                    hideInfoMessage(paymentMethodContainer);
                                }
                            }
                        });
                    });
            }

            let context = {
                paymentForm: paymentForm,
            };

            let paymentMethodExtraConfiguration = {
                currencyCode: currencyIsoCode, // temp fix for https://github.com/Adyen/adyen-web/pull/495 , , Remove after updating the component to 3.18.1 or above
                totalPrice: parseInt(totalAmountInMinorUnits), // temp fix for https://github.com/Adyen/adyen-web/pull/495 , Remove after updating the component to 3.18.1 or above
                amount: { // Use this after above removed
                    value: parseInt(totalAmountInMinorUnits),
                    currency: currencyIsoCode,
                },
                onChange: handleOnChange,
                onSubmit: handleOnSubmit.bind(context),
                onClick: handleOnClick.bind(context),
                onCancel: handleOnCancel.bind(context),
            };

            // Remove after updating the checkout API to version 64 or above
            if (paymentMethod.type.includes('applepay')) {
                paymentMethodExtraConfiguration.configuration = {
                    merchantName: paymentMethodsConfigurations.applePayMerchantName,
                    merchantIdentifier: paymentMethodsConfigurations.applePayMerchantIdentifier,
                };
                
                paymentMethodExtraConfiguration.totalPriceLabel = totalText;
            }

            // Remove after updating the checkout API to version 64 or above
            if (paymentMethod.type.includes('paywithgoogle')) {
                paymentMethodExtraConfiguration.configuration = {
                    gatewayMerchantId: paymentMethodsConfigurations.googlePayGatewayMerchantId,
                    merchantIdentifier: paymentMethodsConfigurations.googlePayMerchantIdentifier,
                };
            }

            if (componentButtonPaymentMethods.includes(paymentMethod.type)) {
                paymentMethodExtraConfiguration.showPayButton = true;
            }

            var configuration = Object.assign(paymentMethod,
                paymentMethodExtraConfiguration);

            try {
                const component = adyenCheckout.create(paymentMethod.type,
                    configuration);

                if ('isAvailable' in component) {
                    component.isAvailable().then(() => {
                        component.mount(containerDOM);

                        if (componentButtonPaymentMethods.includes(
                            paymentMethod.type)) {

                            // Configure to only listen to attribute changes
                            let config = {attributes: true};
                            // Start observing prestaShopPlaceOrderButtonDisabledObserver
                            if (!IS_PRESTA_SHOP_16) {
                                prestaShopPlaceOrderButtonDisabledObserver.observe(
                                    prestaShopPlaceOrderButton.get(0),
                                    config);
                            }
                        }
                    }).catch(e => {
                        if (IS_PRESTA_SHOP_16) {
                            const paymentRow = paymentForm.closest('.adyen-payment');
                            paymentRow.hide();
                        } else {
                            const payWithOption = paymentForm.closest('.js-payment-option-form');
                            const paymentOption = payWithOption.prev();
                            paymentOption.hide();
                            payWithOption.hide();
                        }
                    });
                } else {
                    component.mount(containerDOM);
                }

                return component;

            } catch (err) {
                // The component does not exist yet
            }
        }

        function subscribeToPaymentFormSubmit(
            paymentForm, paymentMethod, component) {
            paymentForm.on('submit', function(e) {
                e.preventDefault();

                if (!paymentMethod.details) {
                    placeOrderAllowed[paymentMethod.type] = true;
                }

                if (!placeOrderAllowed[paymentMethod.type]) {
                    if (!!component && 'showValidation' in component) {
                        component.showValidation();
                    }

                    return;
                }

                if (isPlaceOrderInProgress()) {
                    return false;
                }

                placingOrderStarts(paymentForm);

                var data = {};
                if (!!component && 'data' in component) {
                    data = component.data;
                } else {
                    data.paymentMethod = {'type': paymentMethod.type};
                }

                selectedPaymentMethod = paymentMethod.type;
                // In case the payment method is a storedPayment method overwrite the selectedPaymentMethod
                if ('storedPaymentMethodId' in paymentMethod) {
                    selectedPaymentMethod = paymentMethod.storedPaymentMethodId;
                }

                var paymentData = Object.assign(data, {
                    'isAjax': true,
                });

                processPayment(paymentData, paymentForm, component);
            });
        }

        function processPayment(data, paymentForm, component) {
            var paymentProcessUrl = paymentForm.attr('action');

            $.ajax({
                type: 'POST',
                url: paymentProcessUrl,
                data: data,
                dataType: 'json',
                success: function(response) {
                    processControllerResponse(response, paymentForm, component);
                },
                error: function(response) {
                    paymentForm.find('.error-container').
                        text(response.message).
                        fadeIn(1000);
                    placingOrderEnds(paymentForm);
                },
            });
        }

        function processPaymentsDetails(data) {
            data.isAjax = true;

            return $.ajax({
                type: 'POST',
                url: paymentsDetailsUrl,
                data: data,
                dataType: 'json',
            });
        }

        function processControllerResponse(response, paymentForm, component) {
            switch (response.action) {
                case 'error':
                    // show error message
                    paymentForm.find('.error-container').
                        text(response.message).
                        fadeIn(1000);
                    placingOrderEnds(paymentForm);
                    break;
                case 'redirect':
                    window.location.replace(response.redirectUrl);
                    break;
                case 'action':
                    renderActionComponent(response.response, component);
                    break;
                default:
                    // show error message
                    console.log('Something went wrong on the frontend');
                    placingOrderEnds(paymentForm);
            }
        }

        function renderActionComponent(action, component) {
            // TODO remove when fix is rolled out in a new checkout component version
            delete configuration.data;

            if (action.type === 'threeDS2Challenge' || action.type === 'await') {
                showPopup();
            }

            var actionComponent = window.adyenCheckout = new AdyenCheckout(
                configuration);

            try {
                if (handleActionComponents.includes(action.paymentMethodType)) {
                    component.handleAction(action);
                } else {
                    actionComponent.createFromAction(action).mount('#actionContainer');
                }
            } catch (e) {
                console.log(e);
                hidePopup();
            }
        }

        /**
         * Handles the onChange event of all the components
         * When valid, stores the state.data and sets placeOrderAllowed to true
         * When invalid cleans the stored data
         *
         * @param state
         */
        function handleOnChange(state) {
            placeOrderAllowed[state.data.paymentMethod.type] = state.isValid;
        }

        function handleOnAdditionalDetails(state) {
            hidePopup();
            processPaymentsDetails(state.data).done(function (responseJSON) {
                processControllerResponse(responseJSON,
                    getSelectedPaymentMethod());
            });
        }

        function handleOnSubmit(state) {
            placeOrderAllowed[state.data.paymentMethod.type] = state.isValid;

            if (IS_PRESTA_SHOP_16) {
                this.paymentForm.find('button').prop('disabled', true);
                this.paymentForm.submit();
            } else {
                if (!prestaShopPlaceOrderButton.prop('disabled')) {
                    prestaShopPlaceOrderButton.click();
                } else {
                    this.paymentForm.find('.error-container').text(placeOrderErrorRequiredConditionsText).
                        fadeIn(1000);
                }
            }
        }

        function handleOnClick(resolve, reject) {
            const paymentMethodContainer = getFormPaymentMethodContainer(this.paymentForm);
            const paymentMethodType = paymentMethodContainer.data('local-payment-method');
            // Show message if button is disabled else if not in progress, hide and resolve
            if (prestaShopPlaceOrderButton.prop('disabled') && !isPlaceOrderInProgress()) {
                showRequiredConditionsInfoMessage(paymentMethodContainer);
                // TODO: Remove these paypal specific checks when the component issues are fixed
                if (paymentMethodType === 'paypal') {
                    return false;
                } else {
                    reject(new Error('Terms of service not agreed'));
                }
            } else if (!isPlaceOrderInProgress()) {
                hideInfoMessage(paymentMethodContainer);
                if (paymentMethodType === 'paypal') {
                    return true;
                } else {
                    resolve();
                }
            }
        }

        function handleOnCancel(state, component) {
            // TODO: Stop creating details manually when FOC-42190 is released
            processPaymentsDetails({
                'details': { 'orderID': state.orderID },
            }).done(function(responseJSON) {
                processControllerResponse(responseJSON, getSelectedPaymentMethod(), component);
            });
        }

        function getFormPaymentMethodContainer(paymentForm) {
            return paymentForm.closest('.adyen-payment');
        }

        function getSelectedPaymentMethod() {
            return getSelectedPaymentForm(selectedPaymentMethod);
        }

        function getSelectedPaymentForm(paymentMethodType) {
            return $('.adyen-payment-form-' + paymentMethodType);
        }

        function showPopup() {
            if (IS_PRESTA_SHOP_16) {
                $.fancybox({
                    'autoSize': false,
                    'centerOnScroll': true,
                    'href': '#actionModal',
                    'modal': true,
                    'speedIn': 500,
                    'speedOut': 300,
                    'transitionIn': 'elastic',
                    'transitionOut': 'elastic',
                });
            } else {
                popupModal = $('#actionModal').modal({
                    'keyboard': false,
                    'backdrop': 'static',
                });
            }
        }

        function hidePopup() {
            if (IS_PRESTA_SHOP_16) {
                $.fancybox.close();
            } else {
                if (popupModal) {
                    popupModal.modal('hide');
                }
            }
        }

        function getAdyenGenderByPrestashopType(type) {
            if ('0' === type) {
                return 'MALE';
            }

            if ('1' === type) {
                return 'FEMALE';
            }

            return 'UNKNOWN';
        }

        function placingOrderStarts(paymentForm) {
            placeOrderInProgress = true;
            paymentForm.find('.info-container').
                text(placeOrderInfoInProgressText).
                fadeIn(1000);
            if (IS_PRESTA_SHOP_16) {
                paymentForm.find('button[type="submit"]').
                    prop('disabled', true);
                paymentForm.find('button[type="submit"] i').
                    toggleClass('icon-spinner icon-chevron-right right');
            } else {
                prestaShopPlaceOrderButton.prop('disabled', true);
            }
        }

        function placingOrderEnds(paymentForm) {
            placeOrderInProgress = false;
            paymentForm.find('.info-container').fadeOut(1000);
            if (IS_PRESTA_SHOP_16) {
                paymentForm.find('button[type="submit"]').
                    prop('disabled', false);
                paymentForm.find('button[type="submit"] i').
                    toggleClass('icon-spinner icon-chevron-right right');
            } else {
                prestaShopPlaceOrderButton.prop('disabled', false);
            }
        }
    }

    function showRequiredConditionsInfoMessage(paymentForm) {
        paymentForm.find('.info-container').
            text(placeOrderInfoRequiredConditionsText).
            fadeIn(1000);
    }

    function hideInfoMessage(paymentForm) {
        paymentForm.find('.info-container').fadeOut(1000);
    }

    function isPlaceOrderInProgress() {
        return placeOrderInProgress;
    }
});
