$(document).ready(function () {
    let additionalDataUrl = document.getElementById('adyen-redirect-action-url');
    let checkoutConfigUrl = document.getElementById('adyen-checkout-config-url');
    let checkoutUrl = document.getElementById('adyen-checkout-url');
    let adyenLoader = document.getElementById('adyen-loader');

    const additionalActionDiv = $('[data-adyen-payment-action-container]')[0];

    if (!additionalActionDiv || additionalActionDiv === 'undefined') {
        return;
    }

    const additionalAction = additionalActionDiv.getAttribute('data-adyen-payment-action');
    if (!additionalAction) {
        return;
    }

    adyenLoader.style.display = "flex";

    let checkoutController = new AdyenComponents.CheckoutController({
        "checkoutConfigUrl": checkoutConfigUrl.value,
        "onAdditionalDetails": onAdditionalDetails,
        "sessionStorage": sessionStorage
    });

    checkoutController.handleAdditionalAction(JSON.parse(additionalAction), '[data-adyen-payment-action-container]');

    adyenLoader.style.display = "none";

    function onAdditionalDetails(additionalData) {
        adyenLoader.style.display = "flex";

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
        }).complete(() => {
            adyenLoader.style.display = "none";
        });
    }
})