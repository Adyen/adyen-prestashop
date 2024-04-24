$(document).ready(function () {
    let submitButtonReplacingComponents = ['applepay', 'amazonpay', 'paywithgoogle', 'googlepay', 'paypal'];
    let divPlaceOrder = document.getElementById('payment-confirmation');
    let placeOrder = $(divPlaceOrder).find('[type=submit]');
    let formConditions = document.getElementById('conditions-to-approve');
    let checkBox = $(formConditions).find('[name="conditions_to_approve[terms-and-conditions]"]');
    let checkoutController = null;
    let paymentId = 0;
    let paymentOptions = $('input[name="payment-option"]');
    let adyenPaymentMethods = document.getElementsByClassName('adyen-payment-method');

    $('.payment-option').filter(function () {
        return $(this).find('input[data-module-name="adyenofficial"]').length > 0;
    }).addClass('adyen-image')

    for (let adyenPaymentMethod of adyenPaymentMethods) {
        adyenPaymentMethod.addEventListener('click', (event) => {
            event.stopPropagation();
        })
    }

    checkBox.change(function (event) {
        if (checkBox.is(":checked") && checkoutController) {
            let paymentForm = $("#pay-with-" + paymentId + "-form");
            let type = paymentForm.find('[name=adyen-type]').val();
            if (paymentForm.children(".adyen-payment-method").length > 0
                && !submitButtonReplacingComponents.includes(type)) {
                handleStateChange();
                event.stopPropagation();
            }
        }
    })

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
        let type = paymentForm.find('[name=adyen-type]').val();
        if (paymentForm.children(".adyen-payment-method").length > 0
            && !submitButtonReplacingComponents.includes(type)) {
            mountComponent(paymentForm);
        }
    });

    function mountComponent(paymentForm) {
        let type = paymentForm.find('[name=adyen-type]').val();
        let configUrl = paymentForm.find(".adyen-config-url").val();
        let form = paymentForm.find(".adyen-form-" + type)[0];
        let stored = paymentForm.find('[name=adyen-stored-value]').val();
        let paymentMethodId = paymentForm.find('[name=adyen-payment-method-id]').val();

        checkoutController = new AdyenComponents.CheckoutController({
            "checkoutConfigUrl": configUrl,
            "onStateChange": handleStateChange
        });

        if (stored) {
            checkoutController.mount(type, form, paymentMethodId);

            return;
        }

        checkoutController.mount(type, form);
    }

    function handleStateChange() {
        let paymentForm = $("#pay-with-" + paymentId + "-form");
        let prestaVersion = paymentForm.find('[name=adyen-presta-version]').val();
        let checkbox = $(formConditions).find('[name="conditions_to_approve[terms-and-conditions]"]');

        if (checkoutController.isPaymentMethodStateValid() && ((checkbox.length && checkbox.is(":checked")) || !checkbox.length)) {

            let addData = paymentForm.find('[name=adyen-additional-data]');
            addData.val(checkoutController.getPaymentMethodStateData())
            enablePlaceOrderButton(prestaVersion);

            return
        }

        disablePlaceOrderButton(prestaVersion);
    }

    function disablePlaceOrderButton(prestaVersion) {
        placeOrder.attr('disabled', 'disabled');
        if (prestaVersion >= '1.7.7.2') {
            placeOrder.addClass('disabled');
        }
    }

    function enablePlaceOrderButton(prestaVersion) {
        placeOrder.removeAttr('disabled')
        if (prestaVersion >= '1.7.7.2') {
            placeOrder.removeClass('disabled')
        }
    }
})
