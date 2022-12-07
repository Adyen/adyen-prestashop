jQuery(document).ready(function() {
    const prodSection = $("div:contains('Production Settings'):last").parent();
    const testSection = $("div:contains('Test Settings'):last").parent();
    const notificationSection = $("div:contains('Webhook Settings'):last").parent();
    const radioInput = $("#configuration_form input[name='ADYEN_MODE']");
    populateApiKeys();

    radioInput.change(function () {
        setRequiredParams(this.value);
    });

    /**
     * Show which params are requried based on mode
     *
     * @param mode
     */
    function setRequiredParams(mode) {
        if (mode === 'live') {
            prodSection.find('.control-label').addClass('required');
            notificationSection.find('.control-label').addClass('required');
            testSection.find('.control-label').removeClass('required');
        } else if (mode === 'test') {
            testSection.find('.control-label').addClass('required');
            prodSection.find('.control-label').removeClass('required');
            notificationSection.find('.control-label').removeClass('required');
        }
    }

    /**
     * Populate the api key inputs with some dummy asterisks for better visibility
     */
    function populateApiKeys() {
        if (prodSection.find(":contains('Please fill your API key')").length === 0) {
            prodSection.find(':password').val('****************');
        }

        if (testSection.find(":contains('Please fill your API key')").length === 0) {
            testSection.find(':password').val('****************');
        }
    }
});
