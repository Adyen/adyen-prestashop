/*
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */


jQuery(function ($) {
    if (!window.adyenCheckout) {
        return;
    }

    var encryptedSecurityCode;
    var allValidcard;

    var screenWidth;
    var screenHeight;
    var colorDepth;
    var timeZoneOffset;
    var language;
    var javaEnabled;

    var placeOrderAllowed;
    var popupModal;

    var threeDSProcessUrl;

    // use this object to iterate through the stored payment methods
    var checkoutStoredPaymentMethods = window.adyenCheckout.paymentMethodsResponse.storedPaymentMethods;

    // Iterate through the stored payment methods list we got from the adyen checkout component
    checkoutStoredPaymentMethods.forEach(function (storedPaymentMethod) {

        /*
            storedPaymentMethod.id = $storedPaymentApiId in the oneclick.tpl
            if the container doesn't exits don't try to render the component
        */
        var storedPaymentMethodContainer = $("#cardContainer-" + storedPaymentMethod.id);
        if (!storedPaymentMethodContainer.length) {
            console.log("Something went wrong with the payment method");
            return;
        }

        var storedPaymentMethodConfiguration = $('[data-stored-payment-api-id="' + storedPaymentMethod.id + '"]').data();
        threeDSProcessUrl = storedPaymentMethodConfiguration.threeDsProcessUrl;

        renderStoredPaymentComponent(storedPaymentMethod);

        placeOrderAllowed = false;

        /* Subscribes to the adyen payment method form submission */
        var paymentForm = $("#payment-form.adyen-payment-form-" + storedPaymentMethod.id);
        paymentForm.on('submit', function (e) {
            if (placeOrderAllowed) {
                return;
            }

            e.preventDefault();
            if (!validatePaymentData()) {
                console.log('Validation failed!');
                return;
            }

            processPayment({
                'isAjax': true,
                'encryptedSecurityCode': encryptedSecurityCode,
                'storedPaymentMethodId': storedPaymentMethod.storedPaymentMethodId,
                'browserInfo': {
                    'screenWidth': screenWidth,
                    'screenHeight': screenHeight,
                    'colorDepth': colorDepth,
                    'timeZoneOffset': timeZoneOffset,
                    'language': language,
                    'javaEnabled': javaEnabled
                }
            }, storedPaymentMethod, paymentForm);
        });
    });

    /**
     * Renders checkout card component
     */
    function renderStoredPaymentComponent(storedPaymentMethod) {
        var card = window.adyenCheckout.create('card', Object.assign(
            storedPaymentMethod, {
            onChange: function (state, component) {
                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                    if (state.data.paymentMethod.encryptedSecurityCode) {
                        encryptedSecurityCode = state.data.paymentMethod.encryptedSecurityCode;
                    }

                    javaEnabled = state.data.browserInfo.javaEnabled;
                    colorDepth = state.data.browserInfo.colorDepth;
                    screenWidth = state.data.browserInfo.screenWidth;
                    screenHeight = state.data.browserInfo.screenHeight;
                    timeZoneOffset = state.data.browserInfo.timeZoneOffset;
                    language = state.data.browserInfo.language;

                    allValidcard = true;
                } else {
                    resetFields();
                }
            }
        })).mount("#cardContainer-" + storedPaymentMethod.id);
    }

    /**
     * Place the order (triggers the form to submit)
     */
    function placeOrder(paymentForm) {
        placeOrderAllowed = true;
        paymentForm.submit();
    }

    /**
     * Does the initial payments call with the encrypted data from the card component
     */
    function processPayment(data, storedPaymentMethod, paymentForm) {
        var paymentProcessUrl = paymentForm.attr('action');

        $.ajax({
            type: "POST",
            url: paymentProcessUrl,
            data: data,
            dataType: "json",
            success: function (response) {
                processControllerResponse(response, storedPaymentMethod, paymentForm);
            },
            error: function (response) {
                paymentForm.find('#errors').text(response.message).fadeIn(1000);
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
     * @param data
     */
    function processThreeDS2(data) {
        data.isAjax = true;

        return $.ajax({
            type: "POST",
            url: threeDSProcessUrl,
            data: data,
            dataType: "json",
            done: function (response) {
                return response;
            }
        });
    }

    function renderThreeDS2Component(type, token, storedPaymentMethod, paymentForm) {
        if (type === "IdentifyShopper") {
            window.adyenCheckout.create('threeDS2DeviceFingerprint', {
                fingerprintToken: token,
                onComplete: function (result) {
                    processThreeDS2(result.data).done(function (responseJSON) {
                        processControllerResponse(responseJSON, storedPaymentMethod, paymentForm)
                    });
                },
                onError: function (error) {
                    console.log(JSON.stringify(error));
                }
            }).mount('#threeDS2Container-' + storedPaymentMethod.id);
        } else if (type === "ChallengeShopper") {
            showPopup(storedPaymentMethod);

            window.adyenCheckout.create('threeDS2Challenge', {
                challengeToken: token,
                onComplete: function (result) {
                    hidePopup();
                    processThreeDS2(result.data).done(function (responseJSON) {
                        processControllerResponse(responseJSON, storedPaymentMethod, paymentForm);
                    });
                },
                onError: function (error) {
                    console.log(JSON.stringify(error));
                }
            }).mount('#threeDS2Container-' + storedPaymentMethod.id);
        }
    }

    function showPopup(storedPaymentMethod) {
        if (IS_PRESTA_SHOP_16) {
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
                'href': '#threeDS2Modal-' + storedPaymentMethod.id
            });
        } else {
            popupModal = $('#threeDS2Modal-' + storedPaymentMethod.id).modal();
        }
    }

    function hidePopup() {
        if (IS_PRESTA_SHOP_16) {
            $.fancybox.close();
        } else {
            popupModal.modal("hide");
        }
    }

    /**
     * Decides what to do next based on the payments response
     */
    function processControllerResponse(response, storedPaymentMethod, paymentForm) {
        switch (response.action) {
            case 'error':
                // show error message
                paymentForm.find('#errors').text(response.message).fadeIn(1000);
                break;
            case 'redirect':
                window.location.replace(response.redirectUrl);
                break;
            case 'threeDS2':
                if (!!response.type && !!response.token) {
                    renderThreeDS2Component(response.type, response.token, storedPaymentMethod, paymentForm);
                } else {
                    placeOrder(paymentForm);
                }
                break;
            case 'threeDS1':
                //check if we have all the details
                if (!!response.paRequest &&
                    !!response.md &&
                    !!response.issuerUrl &&
                    !!response.paymentData &&
                    !!response.redirectMethod
                ) {
                    //populate hidden form inputs
                    $('input[name=paymentData]').attr('value', response.paymentData);
                    $('input[name=redirectMethod]').attr('value', response.redirectMethod);
                    $('input[name=issuerUrl]').attr('value', response.issuerUrl);
                    $('input[name=paRequest]').attr('value', response.paRequest);
                    $('input[name=md]').attr('value', response.md);

                    placeOrder(paymentForm);
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


