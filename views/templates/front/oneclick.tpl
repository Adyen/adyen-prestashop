{if !$originKey}
<form id="payment-form" method="post">
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
</form>
{else}

<div class="row adyen-payment">
    <div class="col-xs-12 col-md-6">
        <form id="payment-form" action="{$paymentProcessUrl}" class="adyen-payment-form-{$recurringDetailReference}" method="post">

            <!-- Display payment errors -->
            <div id="errors" role="alert"></div>

            <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
            {if $prestashop16}
                <link rel="stylesheet" href="/js/jquery/plugins/fancybox/jquery.fancybox.css" type="text/css"
                      media="all"/>
                <script type="text/javascript" src="/js/jquery/plugins/fancybox/jquery.fancybox.js"></script>
            {/if}


            <script>
                $(document).ready(function () {
                    let encryptedSecurityCode;
                    let allValidcard;
                    let recurringDetailReference;

                    let screenWidth;
                    let screenHeight;
                    let colorDepth;
                    let timeZoneOffset;
                    let language;
                    let javaEnabled;

                    let placeOrderAllowed;
                    let popupModal;

                    /**
                     * Constructs the first request for the payment call
                     **/
                    function getPaymentData() {
                        debugger;
                        let data = {
                            'isAjax': true,
                            'encryptedSecurityCode': encryptedSecurityCode,
                            'recurringDetailReference': itemArray.recurringDetailReference,
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
                    var oneClickPaymentMethod = "{$oneClickPaymentMethod}".replace(/&quot;/g, '"');
                    if (!!oneClickPaymentMethod) {
                        var itemArray = JSON.parse(oneClickPaymentMethod);
                        renderOneClickComponent();
                    }

                    /* Create adyen checkout with default settings */

                    placeOrderAllowed = false;

                    /* Subscribes to the adyen payment method form submission */
                    $("#payment-form.adyen-payment-form-".concat(itemArray.recurringDetailReference)).on('submit', function(e){
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


                    /**
                     * Renders checkout card component
                     */
                    function renderOneClickComponent() {
                        let card = window.adyenCheckout.create('card', {
                            type: itemArray.type,
                            oneClick: true,
                            details: itemArray.details,
                            storedDetails: itemArray.storedDetails,

                            onChange: function (state, component) {
                                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                                    if (state.data.paymentMethod.encryptedSecurityCode) {
                                        encryptedSecurityCode = state.data.paymentMethod.encryptedSecurityCode;
                                        recurringDetailReference = itemArray.recurringDetailReference;
                                    }
                                    allValidcard = true;
                                } else {
                                    resetFields();
                                }
                            }
                        }).mount("#cardContainer-".concat(itemArray.recurringDetailReference));
                    }

                    /**
                     * Place the order (triggers the form to submit)
                     */
                    function placeOrder() {
                        placeOrderAllowed = true;
                        $("#payment-form.adyen-payment-form-".concat(itemArray.recurringDetailReference)).submit();
                    }

                    /**
                     * Does the initial payments call with the encrypted data from the card component
                     */
                    function processPayment(data) {
                        let paymentProcessUrl = $('#payment-form.adyen-payment-form-'.concat(itemArray.recurringDetailReference)).attr('action');

                        $.ajax({
                            type: "POST",
                            url: paymentProcessUrl,
                            data: data,
                            dataType: "json",
                            success: function(response) {
                                processControllerResponse(response);
                            },
                            error: function(response) {
                                $('#payment-form.adyen-payment-form-'.concat(itemArray.recurringDetailReference)).find('#errors').text(response.message).fadeIn(1000);
                            }
                        });
                    }



                    /**
                     * Reset card details
                     */
                    function resetFields() {
                        encryptedSecurityCode = "";
                        allValidcard = false;
                    }

                    /**
                     * Validates the payment details
                     **/
                    function validatePaymentData() {
                        return allValidcard;
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
                            dataType: "json",
                            done: function(response) {
                                return response;
                            }
                        });
                    }
                    function renderThreeDS2Component(type, token) {
                        debugger;
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
                            }).mount('#threeDS2Container-'.concat("{$recurringDetailReference}"));
                        } else if (type == "ChallengeShopper") {
                            showPopup();

                            adyenCheckout.create('threeDS2Challenge', {
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
                            }).mount('#threeDS2Container-'.concat("{$recurringDetailReference}"));
                        }
                    }

                    function showPopup() {
                        {if $prestashop16}
                        $.fancybox({
                            'autoScale': true,
                            'transitionIn': 'elastic',
                            'transitionOut': 'elastic',
                            'speedIn': 500,
                            'speedOut': 300,
                            'autoDimensions': true,
                            'centerOnScroll': true,
                            'hideOnContentClick': false,
                            'showCloseButton': false,
                            'href' : '#threeDS2Modal-'.concat("{$recurringDetailReference}")
                        });
                        {else}
                        popupModal = $('#threeDS2Modal-'.concat("{$recurringDetailReference}")).modal();
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
                     * Decides what to do next based on the payments response
                     */
                    function processControllerResponse(response) {
                        switch(response.action) {
                            case 'error':
                                // show error message
                                $('#payment-form.adyen-payment-form-'.concat(itemArray.recurringDetailReference)).find('#errors').text(response.message).fadeIn(1000);
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
                });


            </script>
    <div class="checkout-container" id="cardContainer-recurringDetailReference"></div>
    {*<input type="hidden" name="paymentData"/>*}
    {*<input type="hidden" name="redirectMethod"/>*}
    {*<input type="hidden" name="issuerUrl"/>*}
    {*<input type="hidden" name="paRequest"/>*}
    {*<input type="hidden" name="md"/>*}

    {if $prestashop16}
        <div style="display:none">
            <div id="threeDS2Modal-recurringDetailReference">
                <div id="threeDS2Container-recurringDetailReference"></div>
            </div>
        </div>

    {else}
        <div id="threeDS2Modal-recurringDetailReference" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Authentication</h4>
                    </div>
                    <div class="modal-body">
                        <div id="threeDS2Container-recurringDetailReference"></div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
    <script>
        document.getElementById("cardContainer-recurringDetailReference").setAttribute("id", "cardContainer-".concat("{$recurringDetailReference}"));
        document.getElementById("threeDS2Modal-recurringDetailReference").setAttribute("id", "threeDS2Modal-".concat("{$recurringDetailReference}"));
        document.getElementById("threeDS2Container-recurringDetailReference").setAttribute("id", "threeDS2Container-".concat("{$recurringDetailReference}"));
    </script>
{/if}