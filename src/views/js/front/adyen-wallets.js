$(document).ready(function () {
    let submitButtonReplacingComponents = ['applepay', 'amazonpay', 'paywithgoogle', 'googlepay', 'paypal'],
        checkoutController = null,
        checkoutConfigUrl = document.getElementsByClassName('adyen-config-url')[0],
        additionalDataUrl = document.getElementsByClassName('adyen-redirect-action-url')[0],
        checkoutUrl = document.getElementsByClassName('adyen-checkout-url')[0],
        paymentUrl = document.getElementsByClassName('adyen-action-url')[0],
        reference = '',
        paymentData = null,
        type = '',
        url = new URL(location.href),
        amazonCheckoutSessionId = url.searchParams.get('amazonCheckoutSessionId'),
        formConditions = document.getElementById('conditions-to-approve'),
        checkBox = $(formConditions).find('[name="conditions_to_approve[terms-and-conditions]"]'),
        paymentStarted = false,
        paymentOptions = $('input[name="payment-option"]');

    function preventSubmit(event) {
        event.preventDefault(); // Prevents the form from being submitted for ApplePay button
    }

    if (amazonCheckoutSessionId) {
        if (checkBox && !checkBox.is(':checked')) {
            // If not checked, set it to checked
            checkBox.prop('checked', true);
        }

        checkoutController = new AdyenComponents.CheckoutController({
            "checkoutConfigUrl": checkoutConfigUrl.value,
            "showPayButton": true,
            "sessionStorage": sessionStorage,
            "onStateChange": submitOrder,
            "onAdditionalDetails": onAdditionalDetails,
            "onPayButtonClick": onPayButtonClick
        });
        replacePlaceOrderButton('amazonpay');
    }

    checkBox.change(function (event) {
        let selectedPayment = $('input[name="payment-option"]:checked');
        if (selectedPayment.attr('data-module-name') !== 'adyenofficial') {
            replaceAdyenButton();

            return;
        }

        let paymentForm = $("#pay-with-" + paymentId + "-form");
        type = paymentForm.find('[name=adyen-type]').val();
        if (!checkBox.is(":checked") || !submitButtonReplacingComponents.includes(type)) {
            replaceAdyenButton();

            return;
        }

        mountComponent(paymentForm, event);
    });

    if (paymentOptions.length === 1) {
        let paymentForm = $("#pay-with-payment-option-1-form");
        if (paymentForm.children(".adyen-payment-method").length > 0) {
            paymentId = "payment-option-1";
            mountComponent(paymentForm);
        }
    }

    $(document).on("change", "input[name='payment-option']", function () {
        paymentId = this.id;
        let paymentForm = $("#pay-with-" + paymentId + "-form");

        mountComponent(paymentForm);
    });

    function mountComponent(paymentForm, event) {
        replaceAdyenButton();

        if (paymentForm.children(".adyen-payment-method").length === 0) {
            return;
        }

        type = paymentForm.find('[name=adyen-type]').val();
        if (!submitButtonReplacingComponents.includes(type) || (checkBox.length && !checkBox.is(":checked"))) {
            return;
        }

        checkoutController = new AdyenComponents.CheckoutController({
            "checkoutConfigUrl": checkoutConfigUrl.value + '&discountAmount=' + sessionStorage.getItem('totalDiscount'),
            "showPayButton": true,
            "sessionStorage": sessionStorage,
            "onStateChange": submitOrder,
            "onAdditionalDetails": onAdditionalDetails,
            "onPayButtonClick": onPayButtonClick
        });

        replacePlaceOrderButton(type, event);
    }

    function replaceAdyenButton() {
        let orderBtn = $("#payment-confirmation button[type=submit]");
        orderBtn.show();

        let adyenSubmitButton = $("[data-adyen-submit-button]");
        adyenSubmitButton.remove();
    }

    function replacePlaceOrderButton(type, event) {
        let orderBtn = $("#payment-confirmation button[type=submit]");

        orderBtn.parent().append(
            $('<div />')
                .attr('data-adyen-submit-button', 'true')
                .addClass('center-block')
        );
        orderBtn.hide();
        if (event) {
            event.stopPropagation();
        }

        checkoutController.mount(type, '[data-adyen-submit-button]');
    }

    function onPayButtonClick(resolve, reject) {
        formConditions = document.getElementById('conditions-to-approve');
        let checkbox = $(formConditions).find('[name="conditions_to_approve[terms-and-conditions]"]');
        let checked = !checkbox.length ? true : checkbox.is(":checked");

        return checked ? resolve() : reject();
    }

    function onAdditionalDetails(additionalData) {
        if (paymentData) {
            additionalData.paymentData = paymentData
        }

        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: additionalDataUrl.value + "&adyenMerchantReference=" + reference + '&adyenPaymentType=' + type + '&isXHR=1',
            data: additionalData,
            success: function (response) {
                window.location.href = response.nextStepUrl;
            },
            error: function () {
                try {
                    const checkoutUrlObject = new URL(checkoutUrl.value);
                    window.location.href = checkoutUrlObject.href;
                } catch (err) {
                    console.error('Invalid URL, redirection aborted.', err);
                }
            }
        });
    }

    function submitOrder() {
        if (!checkoutController.getPaymentMethodStateData() || paymentStarted) {
            return;
        }

        paymentStarted = true;
        let cardsData = document.getElementsByClassName('adyen-giftcard-state-data');
        let stateData = '';

        for (let element of cardsData) {
            if (element.value !== '') {
                stateData += element.value + ',';
            }
        }

        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: paymentUrl.value + '?isXHR=1',
            data: {
                "adyen-additional-data": checkoutController.getPaymentMethodStateData(),
                "adyen-giftcards-data": '[' + stateData.slice(0, -1) + ']'
            },
            success: function (response) {
                if (response.nextStepUrl) {
                    window.location.href = response.nextStepUrl;
                    return;
                }

                if (!response.action) {
                    try {
                        const checkoutUrlObject = new URL(checkoutUrl.value);
                        window.location.href = checkoutUrlObject.href;
                    } catch (err) {
                        console.error('Invalid URL, redirection aborted.', err);
                    }
                    return;
                }

                reference = response.reference;
                paymentData = null;
                if (response.action.paymentData) {
                    paymentData = response.action.paymentData;
                }

                checkoutController.handleAction(response.action);
            },
            error: function () {
                try {
                    const checkoutUrlObject = new URL(checkoutUrl.value);
                    window.location.href = checkoutUrlObject.href;
                } catch (err) {
                    console.error('Invalid URL, redirection aborted.', err);
                }
            }
        });
    }
})