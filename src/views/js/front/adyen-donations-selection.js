$(document).ready(function () {
    let adyenDonationsDiv = document.getElementById('donation-container');
    let donationsController = null;
    let donationsConfigUrl = null;
    let makeDonationsUrl = null;

    if (adyenDonationsDiv) {
        donationsConfigUrl = adyenDonationsDiv.getAttribute('data-donationsConfigUrl');
        makeDonationsUrl = adyenDonationsDiv.getAttribute('data-makeDonationsUrl');
        donationsController = new AdyenComponents.DonationsController({
            'donationsConfigUrl': donationsConfigUrl,
            'makeDonation': makeDonation
        });

        donationsController.mount(adyenDonationsDiv);
    }

    function makeDonation(data) {
        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: makeDonationsUrl,
            data: data,
            success: function (response) {
                window.location.reload();
            },
            error: function () {
                donationsController.unmount();
                window.location.reload();
            }
        });
    }
})
