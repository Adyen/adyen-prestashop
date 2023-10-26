$(document).ready(function () {
    let payByLinkTitle = $('input[name="adyen-pay-by-link-title"]').val();
    $("#cart_summary_payment_module option[value='adyenofficial']").text(payByLinkTitle);
    let paymentSelectInput = $('#cart_summary_payment_module');
    let expiresAt = $("#adyen-expires-at");
    let paymentSelectDiv = paymentSelectInput.parent().parent();
    let createOrderButton = $('#create-order-button');

    expiresAt.insertAfter(paymentSelectDiv);

    if(paymentSelectInput.val() === 'adyenofficial') {
        expiresAt.show();
    }

    /**
     * Disable create order button if date is in invalid form.
     */
    expiresAt.on('click change' , () => {

        let selectedDate = $("#adyen-expires-at-date").val();
        if(!selectedDate){
            createOrderButton.prop('disabled', true);

            return;
        }

        let selected = new Date(selectedDate);
        let today = new Date();

        if(selected <= today) {
            createOrderButton.prop('disabled', true);

            return;
        }

        createOrderButton.prop('disabled', false);
    });

    /**
     * Display Adyen form if selected.
     */
    paymentSelectInput.on('change', () => {
        let adyenSelected = paymentSelectInput.val() === 'adyenofficial';
        if (adyenSelected) {
            expiresAt.show();

            return;
        }

        expiresAt.hide();
    });
});
