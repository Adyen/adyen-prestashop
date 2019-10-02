{if !$originKey}
<form id="payment-form" method="post">
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
</form>
{else}
    <div class="row adyen-payment">
        <div class="col-xs-12 col-md-6">
            <form id="payment-form" action="{$paymentProcessUrl}" class="adyen-payment-form" method="post">

                <!-- Display payment errors -->
                <div id="errors" role="alert"></div>

                <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
                {if $prestashop16}
                    <link rel="stylesheet" href="/js/jquery/plugins/fancybox/jquery.fancybox.css" type="text/css" media="all" />
                    <script type="text/javascript" src="/js/jquery/plugins/fancybox/jquery.fancybox.js"></script>
                {/if}


                <script>

                    $(document).ready(function () {
                    // window.onload = function(){
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

                    // let adyenCheckout;

                    let placeOrderAllowed;
                    let popupModal;

                    let storeCc;

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
                            'storeCc': storeCc,
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

                        renderCardComponent();
                        fillBrowserInfo();


                    /**
                     * Renders checkout card component
                     */
                    function renderCardComponent() {
                        console.log("card!");
                        // we can now rely on $ within the safety of our "bodyguard" function
                        let card = window.adyenCheckout.create('card', {
                            type: 'card',
                            hasHolderName: true,
                            holderNameRequired: true,
                            enableStoreDetails: true,

                            onChange: function (state, component) {
                                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                                    storeCc = !!state.data.storePaymentMethod;
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
                            window.adyenCheckout.create('threeDS2DeviceFingerprint', {
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
                            showPopup();

                            window.adyenCheckout.create('threeDS2Challenge', {
                                challengeToken: token,
                                onComplete: function (result) {
                                    hidePopup();
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

                    function showPopup() {
                        {if $prestashop16}
                            $.fancybox({
                                'autoDimensions': true,
                                'autoScale': true,
                                'centerOnScroll': true,
                                'href': '#threeDS2Modal',
                                'modal': true,
                                'speedIn': 500,
                                'speedOut': 300,
                                'transitionIn': 'elastic',
                                'transitionOut': 'elastic'
                            });
                        {else}
                        popupModal = $('#threeDS2Modal').modal({
                            'keyboard': false,
                            'backdrop': 'static'
                        });
                        {/if}
                    }

                    function hidePopup() {
                        {if $prestashop16}
                            $.fancybox.close();
                        {else}
                            popupModal.modal("hide");
                        {/if}
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
                                $('#payment-form.adyen-payment-form').find('#errors').text(response.message).fadeIn(1000);
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
                                $('#payment-form.adyen-payment-form').find('#errors').text(response.message).fadeIn(1000);
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
                            case 'threeDS1':
                                //check if we have all the details
                                if (!!response.paRequest &&
                                    !!response.md &&
                                    !!response.issuerUrl &&
                                    !!response.paymentData &&
                                    !!response.redirectMethod
                                ){
                                    //populate hidden form inputs
                                    $('input[name=paymentData]').attr('value',response.paymentData);
                                    $('input[name=redirectMethod]').attr('value',response.redirectMethod);
                                    $('input[name=issuerUrl]').attr('value',response.issuerUrl);
                                    $('input[name=paRequest]').attr('value',response.paRequest);
                                    $('input[name=md]').attr('value',response.md);

                                    placeOrder();
                                } else {
                                    console.log("Something went wrong on the frontend");
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
                        let threeDSProcessUrl = "{$threeDSProcessUrl nofilter}";

                        data['isAjax'] = true;

                        return $.ajax({
                            type: "POST",
                            url: threeDSProcessUrl,
                            data: data,
                            dataType: "json"
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
                    });
                </script>


                <div class="checkout-container" id="cardContainer"></div>
                <input type="hidden" name="paymentData"/>
                <input type="hidden" name="redirectMethod"/>
                <input type="hidden" name="issuerUrl"/>
                <input type="hidden" name="paRequest"/>
                <input type="hidden" name="md"/>

                {if $prestashop16}
                    <div style="display:none">
                        <div id="threeDS2Modal">
                            <div id="threeDS2Container"></div>
                        </div>
                    </div>

                {else}
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
                {/if}

                {if $prestashop16}
                    <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                     {l s='Pay' mod='adyen'} <i class="icon-chevron-right right"></i> </span></button>
                {/if}
            </form>
        </div>
    </div>
{/if}