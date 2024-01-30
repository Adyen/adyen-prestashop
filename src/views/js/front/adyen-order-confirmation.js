$(document).ready(function () {
    let additionalAction = $('#adyen-additional-action');
    let checkoutConfigUrl = document.getElementById('adyen-checkout-config-url');
    let additionalDataUrl = $('#adyen-additional-data-url');

    if (additionalAction.html() === '' || additionalAction.html() === undefined) {
        return;
    }

    let additionalActionData = JSON.parse(additionalAction.html());
    if (!additionalActionData || !additionalActionData.type) {
        return;
    }

    let checkoutController = new AdyenComponents.CheckoutController({
        "checkoutConfigUrl": checkoutConfigUrl.value,
        "onAdditionalDetails": onAdditionalDetails,
        "sessionStorage": sessionStorage
    });

    checkoutController.handleAdditionalAction(additionalActionData, $('#adyen-additional-data')[0]);

    function onAdditionalDetails(additionalData) {
        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: additionalDataUrl.val(),
            data: additionalData,
            success: function (response) {
            }
        });
    }
})