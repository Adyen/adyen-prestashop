$(document).ready(function () {
    let additionalDataUrl = document.getElementById('adyen-redirect-action-url');
    let checkoutConfigUrl = document.getElementById('adyen-checkout-config-url');
    let checkoutUrl = document.getElementById('adyen-checkout-url');

    const additionalActionDiv = $('[data-adyen-payment-action-container]')[0];

    if (!additionalActionDiv || additionalActionDiv === 'undefined') {
        return;
    }

    const additionalAction = additionalActionDiv.getAttribute('data-adyen-payment-action');
    if (!additionalAction) {
        return;
    }

    let checkoutController = new AdyenComponents.CheckoutController({
        "checkoutConfigUrl": checkoutConfigUrl.value,
        "onAdditionalDetails": onAdditionalDetails,
        "sessionStorage": sessionStorage
    });

    checkoutController.handleAdditionalAction(JSON.parse(additionalAction), '[data-adyen-payment-action-container]');

    function onAdditionalDetails(additionalData) {
        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: additionalDataUrl.value,
            data: additionalData,
            success: function (response) {
                window.location.href = response.nextStepUrl;
            },
            error: function () {
                window.location.href = checkoutUrl.value;
            }
        });
    }
})