var Adyen = Adyen || {};

$(document).ready(function () {

    disableCheckbox();

    if (!$('input[name="adyen-refund-supported"]').val()) {
        disableRefundButtons()
    }

    $('#adyen-capture-button').click(function () {
        const endpointURL = $('input[name="adyen-capture-url"]').val();
        const orderId = $('input[name="adyen-orderId"]').val();
        const captureAmount = $('input[name="adyen-capture-amount"]').val();

        Adyen.adyenAjaxService().post(endpointURL, {
            'orderId': orderId,
            'captureAmount': captureAmount
        }, (response, status) => {
            location.reload();
        });
    });

    $('input[name="adyen-capture-amount"]').on('input', function () {
        let captureAmount = $(this).val();
        const capturableAmount = $('input[name="adyen-capturable-amount"]').val();

        if (parseFloat(captureAmount) > parseFloat(capturableAmount)) {
            disableCaptureButton()

            return;
        }

        enableCaptureButton()
    });

    function disableCaptureButton() {
        let button = $('#adyen-capture-button');
        button.prop('disabled', true);
    }

    function enableCaptureButton() {
        let button = $('#adyen-capture-button');
        button.prop('disabled', false);
    }

    function disableRefundButtons() {
        let prestaVersion =  $('input[name="adyen-presta-version"]').val();
        if(prestaVersion < '1.7.7') {
            $('#desc-order-partial_refund').attr('disabled', 'disabled');
            $('#desc-order-standard_refund').attr('disabled', 'disabled');

            return
        }

        $('.btn-action.return-product-display').prop('disabled', true);
        $('.btn-action.partial-refund-display').prop('disabled', true);
    }

    function disableCheckbox() {
        let prestaVersion =  $('input[name="adyen-presta-version"]').val();
        if(prestaVersion < '1.7.7') {
            let generateCreditSlipCheckbox = $('#generateCreditSlip');
            if (!generateCreditSlipCheckbox.is(':checked')) {
                generateCreditSlipCheckbox.prop('checked', true);
            }
            $('#spanShippingBack').css('display', 'block');

            generateCreditSlipCheckbox.on('click', function(event) {
                event.preventDefault();
                $('#spanShippingBack').css('display', 'block');
            });
        }
    }
});
