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

jQuery(document).ready(function () {
    if (!window.ADYEN_CHECKOUT_CONFIG) {
        return;
    }

    var placeOrderInProgress = false;
    var data = {};
    var placeOrderAllowed;
    var popupModal;

    var notSupportedComponents = ['paypal'];

    var invoiceAddress = prestashop.customer.addresses[selectedInvoiceAddressId];
    var deliveryAddress = prestashop.customer.addresses[selectedDeliveryAddressId];

    var phoneNumber = invoiceAddress.phone ? invoiceAddress.phone : invoiceAddress.phone_mobile;

    var personalDetails = {
        firstName: prestashop.customer.firstname,
        lastName: prestashop.customer.lastname,
        shopperEmail: prestashop.customer.email,
        telephoneNumber: phoneNumber,
        gender: getAdyenGenderByPrestashopType(prestashop.customer.gender.type),
        dateOfBirth: prestashop.customer.birthday
    }

    var billingAddress = {
        city: invoiceAddress.city,
        country: invoiceAddress.country_iso,
        houseNumberOrName: invoiceAddress.address2,
        postalCode: invoiceAddress.postcode,
        street: invoiceAddress.address1
    }

    var deliveryAddress = {
        city: deliveryAddress.city,
        country: deliveryAddress.country_iso,
        houseNumberOrName: deliveryAddress.address2,
        postalCode: deliveryAddress.postcode,
        street: deliveryAddress.address1
    }

    var configuration = Object.assign(
        ADYEN_CHECKOUT_CONFIG,
        {
            hasHolderName: true,
            holderNameRequired: false,
            enableStoreDetails: !!isUserLoggedIn,
            countryCode: invoiceAddress.country_iso,
            data: {
                billingAddress: billingAddress,
                deliveryAddress: deliveryAddress,
                personalDetails: personalDetails
            },
            onAdditionalDetails: handleOnAdditionalDetails
        }
    )

    window.adyenCheckout = new AdyenCheckout(configuration);

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

        var component = renderPaymentComponent(paymentMethod, paymentMethodContainer);

        // Use data to reteive the payment method data
        subscribeToPaymentFormSubmit(paymentForm, paymentMethod, component);
    });

    var checkoutStoredPaymentMethods = window.adyenCheckout.paymentMethodsResponse.storedPaymentMethods;

    // Iterate through the stored payment methods list we got from the adyen checkout component
    checkoutStoredPaymentMethods.forEach(function (storedPaymentMethod) {

        //  storedPaymentMethod.id = $storedPaymentApiId in the stored-payment-method.tpl
        //  don't try to render the component if the container doesn't exist
        var paymentMethodContainer = $('[data-stored-payment-api-id="' + storedPaymentMethod.id + '"]');

        // container doesn't exist, something went wrong on the template side
        if (!paymentMethodContainer.length) {
            return;
        }

        var component = renderPaymentComponent(storedPaymentMethod, paymentMethodContainer);

        /* Subscribes to the adyen payment method form submission */
        var paymentForm = $(".adyen-payment-form-" + storedPaymentMethod.id);

        subscribeToPaymentFormSubmit(paymentForm, component);
    });

    function renderPaymentComponent(paymentMethod, paymentMethodContainer) {

        if (notSupportedComponents.includes(paymentMethod.type)) {
            return;
        }

        var configuration = Object.assign(paymentMethod, {
            'onChange':handleOnChange
        });

        try {
            return adyenCheckout
            .create(paymentMethod.type, configuration)
            .mount(paymentMethodContainer.find('[data-adyen-payment-container]').get(0));
        } catch (err) {
            console.log(paymentMethod.type);
            console.log(err);
            // The component does not exist yet
        }
    }

    function subscribeToPaymentFormSubmit(paymentForm, paymentMethod, component) {
        paymentForm.on('submit', function(e) {
            e.preventDefault();

            if (!paymentMethod.details) {
                placeOrderAllowed = true;
            }

            if (!placeOrderAllowed) {
                component.showValidation();
                return;
            }

            if (isPlaceOrderInProgress()) {
                return false;
            }

            placingOrderStarts(paymentForm);

            // If data is not set (component doesn't exist) prefill the type
            if (!data) {
                data = {};
                data.paymentMethod = {'type': paymentMethod.type};
            } else if (typeof data.paymentMethod === 'undefined') {
                data.paymentMethod = {'type': paymentMethod.type};
            } else if (typeof data.paymentMethod.type === 'undefined') {
                data.paymentMethod = {'type': paymentMethod.type};
            }

            var paymentData = Object.assign(data, {
                'isAjax': true,
            });

            processPayment(paymentData, paymentForm);
        });
    }

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
                placingOrderEnds(paymentForm);
            }
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

    function processControllerResponse(response, paymentForm) {
        switch (response.action) {
            case 'error':
                // show error message
                paymentForm.find('.error-container').text(response.message).fadeIn(1000);
                placingOrderEnds(paymentForm);
                break;
            case 'redirect':
                window.location.replace(response.redirectUrl);
                break;
            case 'action':
                renderActionComponent(response.response);
                break;
            default:
                // show error message
                console.log("Something went wrong on the frontend");
                placingOrderEnds(paymentForm);
        }
    }

    function renderActionComponent(action) {
        // TODO remove when fix is rolled out in a new checkout component version
        delete configuration.data;

        var actionComponent = window.adyenCheckout = new AdyenCheckout(configuration);

        try {
            actionComponent.createFromAction(action).mount('#actionContainer');

            if (action.type === 'threeDS2Challenge') {
                showPopup();
            }
        } catch (e) {
            console.log(e);
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
        if (state.isValid) {
            data = state.data;
            placeOrderAllowed = true;
        } else {
            placeOrderAllowed = false;
            resetFields();
        }
    }

    function handleOnAdditionalDetails(state) {
        hidePopup();
        processPaymentsDetails(state.data).done(function(responseJSON) {
            processControllerResponse(responseJSON, getSelectedPaymentMethod());
        });
    }

    function getSelectedPaymentMethod() {
        return getSelectedPaymentForm(data.paymentMethod.type);
    }

    function getSelectedPaymentForm(paymentMethodType) {
        return $(".adyen-payment-form-" + paymentMethodType);
    }

    function showPopup() {
        if (IS_PRESTA_SHOP_16) {
            $.fancybox({
                'autoDimensions': true,
                'autoScale': true,
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

    function getAdyenGenderByPrestashopType(type)
    {
        if ("0" === type) {
            return 'MALE';
        }

        if ("1" === type) {
            return 'FEMALE';
        }

        return 'UNKNOWN'
    }

    function resetFields() {
        data = null;
    }

    function isPlaceOrderInProgress() {
        return placeOrderInProgress;
    }

    function placingOrderStarts(paymentForm) {
        placeOrderInProgress = true;
        paymentForm.find('button[type="submit"]').prop('disabled', true);
        paymentForm.find('button[type="submit"] i').
            toggleClass('icon-spinner icon-chevron-right right');
    }

    function placingOrderEnds(paymentForm) {
        placeOrderInProgress = false;
        paymentForm.find('button[type="submit"]').prop('disabled', false);
        paymentForm.find('button[type="submit"] i').
            toggleClass('icon-spinner icon-chevron-right right');
    }
});
