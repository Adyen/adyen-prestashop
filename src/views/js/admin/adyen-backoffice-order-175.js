$(document).ready(function () {
    const summary = document.getElementById('summary_part');
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutationRecord) {

            let paymentSelect = $('#payment_module_name');
            let expiresAt = $("#adyen-expires-at");
            let createOrderButton = $("[name='submitAddOrder']");
            let paymentSelectDiv = paymentSelect.parent().parent();
            expiresAt.insertAfter(paymentSelectDiv);
            $("#payment_module_name option[value='adyenofficial']").text($('input[name="adyen-pay-by-link-title"]').val());


            if(paymentSelect.val() === 'adyenofficial') {
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
            paymentSelect.on('change', () => {
                let adyenSelected = paymentSelect.val() === 'adyenofficial';
                if (adyenSelected) {
                    expiresAt.show();

                    return;
                }

                expiresAt.hide();
            });

        })

    });

    if (summary) {
        observer.observe(summary, {attributes: true, attributeFilter: ['style']});
    }
});