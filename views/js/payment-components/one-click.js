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
    var recurringDetailReference;

    var screenWidth;
    var screenHeight;
    var colorDepth;
    var timeZoneOffset;
    var language;
    var javaEnabled;

    var placeOrderAllowed;
    var popupModal;

    var threeDSProcessUrl;

    $('[data-one-click-payment]').each(function (index, element) {
        var oneClickPaymentConfiguration = $(element).data();
        var oneClickPaymentMethod = oneClickPaymentConfiguration.oneClickPayment;
        if (oneClickPaymentMethod) {
            renderOneClickComponent(oneClickPaymentMethod);
            fillBrowserInfo();
        }

        threeDSProcessUrl = oneClickPaymentConfiguration.threeDsProcessUrl;

        /* Create adyen checkout with default settings */

        placeOrderAllowed = false;

        /* Subscribes to the adyen payment method form submission */
        var paymentForm = $("#payment-form.adyen-payment-form-" + oneClickPaymentMethod.recurringDetailReference);
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
                'recurringDetailReference': oneClickPaymentMethod.recurringDetailReference,
                'browserInfo': {
                    'screenWidth': screenWidth,
                    'screenHeight': screenHeight,
                    'colorDepth': colorDepth,
                    'timeZoneOffset': timeZoneOffset,
                    'language': language,
                    'javaEnabled': javaEnabled
                }
            }, oneClickPaymentMethod, paymentForm);
        });
    });

    /**
     * Renders checkout card component
     */
    function renderOneClickComponent(oneClickPaymentMethod) {
        var card = window.adyenCheckout.create('card', {
            type: oneClickPaymentMethod.type,
            oneClick: true,
            details: oneClickPaymentMethod.details,
            storedDetails: oneClickPaymentMethod.storedDetails,

            onChange: function (state, component) {
                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                    if (state.data.paymentMethod.encryptedSecurityCode) {
                        encryptedSecurityCode = state.data.paymentMethod.encryptedSecurityCode;
                        recurringDetailReference = oneClickPaymentMethod.recurringDetailReference;
                    }
                    allValidcard = true;
                } else {
                    resetFields();
                }
            }
        }).mount("#cardContainer-" + oneClickPaymentMethod.recurringDetailReference);
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
    function processPayment(data, oneClickPaymentMethod, paymentForm) {
        var paymentProcessUrl = paymentForm.attr('action');

        $.ajax({
            type: "POST",
            url: paymentProcessUrl,
            data: data,
            dataType: "json",
            success: function (response) {
                processControllerResponse(response, oneClickPaymentMethod, paymentForm);
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

    function renderThreeDS2Component(type, token, oneClickPaymentMethod, paymentForm) {
        if (type === "IdentifyShopper") {
            adyenCheckout.create('threeDS2DeviceFingerprint', {
                fingerprintToken: token,
                onComplete: function (result) {
                    processThreeDS2(result.data).done(function (responseJSON) {
                        processControllerResponse(responseJSON, oneClickPaymentMethod, paymentForm)
                    });
                },
                onError: function (error) {
                    console.log(JSON.stringify(error));
                }
            }).mount('#threeDS2Container-' + oneClickPaymentMethod.recurringDetailReference);
        } else if (type === "ChallengeShopper") {
            showPopup(oneClickPaymentMethod);

            adyenCheckout.create('threeDS2Challenge', {
                challengeToken: token,
                onComplete: function (result) {
                    hidePopup();
                    processThreeDS2(result.data).done(function (responseJSON) {
                        processControllerResponse(responseJSON, oneClickPaymentMethod, paymentForm);
                    });
                },
                onError: function (error) {
                    console.log(JSON.stringify(error));
                }
            }).mount('#threeDS2Container-' + oneClickPaymentMethod.recurringDetailReference);
        }
    }

    function showPopup(oneClickPaymentMethod) {
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
                'href': '#threeDS2Modal-' + oneClickPaymentMethod.recurringDetailReference
            });
        } else {
            popupModal = $('#threeDS2Modal-' + oneClickPaymentMethod.recurringDetailReference).modal();
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
    function processControllerResponse(response, oneClickPaymentMethod, paymentForm) {
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
                    renderThreeDS2Component(response.type, response.token, oneClickPaymentMethod, paymentForm);
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

    /**
     *  Using the threeds2-js-utils.js to fill browserinfo
     */
    function fillBrowserInfo() {
        var browserInfo = ThreedDS2Utils.getBrowserInfo();

        javaEnabled = browserInfo.javaEnabled;
        colorDepth = browserInfo.colorDepth;
        screenWidth = browserInfo.screenWidth;
        screenHeight = browserInfo.screenHeight;
        timeZoneOffset = browserInfo.timeZoneOffset;
        language = browserInfo.language;
    }
});


