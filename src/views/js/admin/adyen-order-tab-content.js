var Adyen = Adyen || {};

$(document).ready(function () {

    disableCheckbox();

    let refundsSupported = $('input[name="adyen-refund-supported"]');
    let refund = false;

    for (let supported of refundsSupported) {
        refund = refund || supported.value;
    }

    if (!refund) {
        disableRefundButtons()
    }

    $('[name="adyen-capture-button"]').click(function () {
        const endpointURL = $(this).parent().find('[name="adyen-capture-url"]').val();
        const orderId = $(this).parent().find('[name="adyen-orderId"]').val();
        const captureAmount = $(this).parent().find('[name="adyen-capture-amount"]');
        const pspReference = $(this).parent().find('[name="adyen-psp-reference"]').val();
        let capturedAmount = $(this).parent().find('[name="adyen-capture-url"]').val();

        if (captureAmount) {
            capturedAmount = captureAmount.val();
        }

        Adyen.adyenAjaxService().post(endpointURL, {
            'orderId': orderId,
            'captureAmount': capturedAmount,
            'pspReference': pspReference
        }, (response, status) => {
            location.reload();
        });
    });


    $('input[name="adyen-capture-amount"]').on('input', function () {
        let captureAmount = $(this).val();
        const capturableAmount = $('input[name="adyen-capturable-amount"]').val();
        let button = $(this).parent().find('button[name="adyen-capture-button"]');

        if (parseFloat(captureAmount) > parseFloat(capturableAmount)) {
            button.prop('disabled', true);

            return;
        }

        button.prop('disabled', false);
    });

    $('#adyen-extend-authorization-button').click(function () {
        const endpointURL = $('input[name="adyen-extend-authorization-url"]').val();
        const orderId = $('input[name="adyen-orderId"]').val();

        Adyen.adyenAjaxService().post(endpointURL, {
            'orderId': orderId
        }, (response, status) => {
            location.reload();
        });
    });

    function disableRefundButtons() {
        let prestaVersion = $('input[name="adyen-presta-version"]').val();
        if (prestaVersion < '1.7.7') {
            $('#desc-order-partial_refund').attr('disabled', 'disabled');
            $('#desc-order-standard_refund').attr('disabled', 'disabled');

            return
        }

        $('.btn-action.return-product-display').prop('disabled', true);
        $('.btn-action.partial-refund-display').prop('disabled', true);
    }

    function disableCheckbox() {
        let prestaVersion = $('input[name="adyen-presta-version"]').val();
        if (prestaVersion < '1.7.7') {
            let generateCreditSlipCheckbox = $('#generateCreditSlip');
            if (!generateCreditSlipCheckbox.is(':checked')) {
                generateCreditSlipCheckbox.prop('checked', true);
            }
            $('#spanShippingBack').css('display', 'block');

            generateCreditSlipCheckbox.on('click', function (event) {
                event.preventDefault();
                $('#spanShippingBack').css('display', 'block');
            });
        }
    }

    $('#adyen-copy-payment-link').click(function () {
        navigator.clipboard.writeText($("#adyen-payment-link").val());
    });

    $('#adyen-generate-payment-link-button').click(function () {
        const endpointURL = $('input[name="adyen-generate-payment-link-url"]').val();
        const orderId = $('input[name="adyen-orderId"]').val();
        const amount = $('input[name="adyen-payment-link-amount"]').val();

        Adyen.adyenAjaxService().post(endpointURL, {
            'orderId': orderId,
            'amount': amount
        }, (response, status) => {
            location.reload();
        });
    });
});
