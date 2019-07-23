{if !$originKey}
<form id="payment-form" method="post">
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
</form>
{else}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <form id="payment-form" action="{$paymentProcessUrl}" class="adyen-payment-form" method="post">
                <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
                <script>

                    let holderName;
                    let encryptedCardNumber;
                    let encryptedExpiryMonth;
                    let encryptedExpiryYear;
                    let encryptedSecurityCode;
                    let allValidcard;

                    let screenWidth;
                    let screenHeight;
                    let colorDepth;
                    let timeZoneOffset;
                    let language;
                    let javaEnabled;

                    let adyenCheckout;

                    let placeOrderAllowed;

                    /**
                     * Constructs the first request for the payment call
                     **/
                    function getPaymentData() {

                        let data = {
                            'isAjax': true,
                            'holderName': holderName,
                            'encryptedCardNumber': encryptedCardNumber,
                            'encryptedExpiryMonth': encryptedExpiryMonth,
                            'encryptedExpiryYear': encryptedExpiryYear,
                            'encryptedSecurityCode': encryptedSecurityCode,
                            'browserInfo': {
                                'screenWidth': screenWidth,
                                'screenHeight': screenHeight,
                                'colorDepth': colorDepth,
                                'timeZoneOffset': timeZoneOffset,
                                'language': language,
                                'javaEnabled': javaEnabled
                            }
                        };

                        return data;
                    }

                    /**
                     * Validates the payment details
                     **/
                    function validatePaymentData() {
                        return allValidcard;
                    }

                    /* Create adyen checkout with default settings */
                    $(document).ready(function () {

                        placeOrderAllowed = false;

                        /* Subscribes to the adyen payment method form submission */
                        $("#payment-form.adyen-payment-form").on('submit', function(e){
                            if (!placeOrderAllowed) {
                                e.preventDefault();

                                if (!validatePaymentData()) {
                                    console.log('Validation failed!');
                                    return false;
                                }

                                let data = getPaymentData();
                                processPayment(data);

                                return false;
                            } else {
                                return true;
                            }
                        });

                        adyenCheckout = new AdyenCheckout({
                            locale: "{$locale}",
                            originKey: "{$originKey}",
                            environment: "{$environment}",
                            risk: {
                                enabled: false
                            }
                        });

                        renderCardComponent();
                        fillBrowserInfo();
                    });

                    /**
                     * Renders checkout card component
                     */
                    function renderCardComponent() {
                        // we can now rely on $ within the safety of our "bodyguard" function
                        let card = adyenCheckout.create('card', {
                            type: 'card',
                            hasHolderName: true,
                            holderNameRequired: true,

                            onChange: function (state, component) {
                                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                                    holderName = state.data.paymentMethod.holderName;
                                    encryptedCardNumber = state.data.paymentMethod.encryptedCardNumber;
                                    encryptedExpiryMonth = state.data.paymentMethod.encryptedExpiryMonth;
                                    encryptedExpiryYear = state.data.paymentMethod.encryptedExpiryYear;
                                    if (state.data.paymentMethod.encryptedSecurityCode) {
                                        encryptedSecurityCode = state.data.paymentMethod.encryptedSecurityCode;
                                    }

                                    allValidcard = true;
                                } else {
                                    resetFields();
                                }
                            }
                        }).mount("#cardContainer");
                    }

                    /**
                     * Rendering the 3DS2.0 components
                     * To do the device fingerprint at the response of IdentifyShopper render the threeDS2DeviceFingerprint
                     * component
                     * To render the challenge for the customer at the response of ChallengeShopper render the
                     * threeDS2Challenge component
                     * Both of them is going to be rendered in a Magento dialog popup
                     *
                     * @param type
                     * @param token
                     */
                    function renderThreeDS2Component(type, token) {
                        if (type == "IdentifyShopper") {
                            adyenCheckout.create('threeDS2DeviceFingerprint', {
                                fingerprintToken: token,
                                onComplete: function (result) {
                                    processThreeDS2(result.data).done(function (responseJSON) {
                                        processControllerResponse(responseJSON)
                                    });
                                },
                                onError: function (error) {
                                    console.log(JSON.stringify(error));
                                }
                            }).mount('#threeDS2Container');
                        } else if (type == "ChallengeShopper") {

                            let popupModal = $('#threeDS2Modal').modal();
                            popupModal.modal("show");

                            adyenCheckout.create('threeDS2Challenge', {
                                challengeToken: token,
                                onComplete: function (result) {
                                    popupModal.modal("hide");
                                    processThreeDS2(result.data).done(function (responseJSON) {
                                        processControllerResponse(responseJSON);
                                    });
                                },
                                onError: function (error) {
                                    console.log(JSON.stringify(error));
                                }
                            }).mount('#threeDS2Container');
                        }
                    }

                    /**
                     * Place the order (triggers the form to submit)
                     */
                    function placeOrder() {
                        placeOrderAllowed = true;
                        $("#payment-form.adyen-payment-form").submit();
                    }

                    /**
                     * Does the initial payments call with the encrypted data from the card component
                     */
                    function processPayment(data) {
                        let paymentProcessUrl = $('#payment-form.adyen-payment-form').attr('action');

                        $.ajax({
                            type: "POST",
                            url: paymentProcessUrl,
                            data: data,
                            dataType: "json",
                            success: function(response) {
                                processControllerResponse(response);
                            },
                            error: function(response) {
                                // todo show error
                                console.log(response);
                            }
                        });
                    }

                    /**
                     * Decides what to do next based on the payments response
                     */
                    function processControllerResponse(response) {
                        switch(response.action) {
                            case 'error':
                                // show error message
                                console.log(response.message);
                                break;
                            case 'redirect':
                                window.location.replace(response.redirectUrl);
                                break;
                            case 'threeDS2':
                                if (!!response.type && !!response.token) {
                                    renderThreeDS2Component(response.type, response.token);
                                } else {
                                    placeOrder();
                                }

                                break;
                            default:
                                // show error message
                                console.log("Something went wrong on the frontend");
                        }
                    }

                    /**
                     * The results that the 3DS2 components returns in the onComplete callback needs to be sent to the
                     * backend to the threeDSProcess endpoint and based on the response render a new threeDS2
                     * component or place the order (validateThreeDS2OrPlaceOrder)
                     * @param response
                     */
                    function processThreeDS2(data) {
                        let threeDSProcessUrl = $("<div>").html("{$threeDSProcessUrl}").text();

                        data['isAjax'] = true;

                        return $.ajax({
                            type: "POST",
                            url: threeDSProcessUrl,
                            data: data,
                            dataType: "json",
                            done: function(response) {
                                return response;
                            }
                        });
                    }

                    /**
                     * Reset card details
                     */
                    function resetFields() {
                        holderName = "";
                        encryptedCardNumber = "";
                        encryptedExpiryMonth = "";
                        encryptedExpiryYear = "";
                        encryptedSecurityCode = "";
                        allValidcard = false;
                    }

                    /**
                     *  Using the threeds2-js-utils.js to fill browserinfo
                     */
                    function fillBrowserInfo() {
                        let browserInfo = ThreedDS2Utils.getBrowserInfo();

                        javaEnabled = browserInfo.javaEnabled;
                        colorDepth = browserInfo.colorDepth;
                        screenWidth = browserInfo.screenWidth;
                        screenHeight = browserInfo.screenHeight;
                        timeZoneOffset = browserInfo.timeZoneOffset;
                        language = browserInfo.language;
                    }

                </script>

                <div class="checkout-container" id="cardContainer"></div>

                <div id="threeDS2Modal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title">Authentication</h4>
                            </div>
                            <div class="modal-body">
                                <div id="threeDS2Container"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {if $prestashop16}
                    <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                     {l s='Pay' mod='adyen'} <i class="icon-chevron-right right"></i> </span></button>
                {/if}
            </form>
        </div>
    </div>
{/if}