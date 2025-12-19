$(document).ready(function () {

    let additionalAction = $('#adyen-additional-action');
    let checkoutConfigUrl = document.getElementById('adyen-checkout-config-url');

    sessionStorage.removeItem('remainingAmount');
    sessionStorage.removeItem('totalDiscount');
    sessionStorage.removeItem('minorTotalDiscount');

    if (additionalAction.html() === '' || additionalAction.html() === undefined) {
        return;
    }

    let additionalActionData = JSON.parse(additionalAction.html());
    if (!additionalActionData || !additionalActionData.type) {
        return;
    }

    let checkoutController = new AdyenComponents.CheckoutController({
        "checkoutConfigUrl": checkoutConfigUrl.value,
        "sessionStorage": sessionStorage
    });

    checkoutController.handleAdditionalAction(additionalActionData, $('#adyen-additional-data')[0]);
})
