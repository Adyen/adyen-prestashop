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
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

jQuery(function ($) {
    if (!window.adyenCheckout) {
        return;
    }

    var allValidCard;
    var placeOrderAllowed;
    var popupModal;
    var data;

    /* Create adyen checkout with default settings */
    placeOrderAllowed = false;

    /* Subscribes to the adyen payment method form submission */
    var paymentForm = $(".adyen-payment-form");
    paymentForm.on('submit', function (e) {
        if (!placeOrderAllowed) {
            e.preventDefault();

            if (!allValidCard) {
                console.log('Validation failed!');
                return false;
            }

            processPayment({
                'isAjax': true,
                'browserInfo': data.browserInfo,
                'paymentMethod': data.paymentMethod,
                'storePaymentMethod': data.storePaymentMethod
            });

            return false;
        } else {
            return true;
        }
    });

    renderCardComponent();

    /**
     * Renders checkout card component
     */
    function renderCardComponent() {
        // we can now rely on $ within the safety of our "bodyguard" function
        var card = window.adyenCheckout.create('card', {
            type: 'card',
            hasHolderName: true,
            holderNameRequired: true,
            enableStoreDetails: !!parseInt(paymentForm.data().isLoggedInUser, 10),

            onChange: function (state, component) {
                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                    data = state.data;
                    allValidCard = true;
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
     * Both of them are going to be rendered in a popup
     *
     * @param type
     * @param token
     */
    function renderThreeDS2Component(type, token) {
        if (type === "IdentifyShopper") {
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
        } else if (type === "ChallengeShopper") {
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
        if (IS_PRESTA_SHOP_16) {
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
        } else {
            popupModal = $('#threeDS2Modal').modal({
                'keyboard': false,
                'backdrop': 'static'
            });
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
     * Place the order (triggers the form to submit)
     */
    function placeOrder() {
        placeOrderAllowed = true;
        paymentForm.submit();
    }

    /**
     * Does the initial payments call with the encrypted data from the card component
     */
    function processPayment(data) {
        var paymentProcessUrl = $('.adyen-payment-form').attr('action');

        $.ajax({
            type: "POST",
            url: paymentProcessUrl,
            data: data,
            dataType: "json",
            success: function (response) {
                processControllerResponse(response);
            },
            error: function (response) {
                $('.adyen-payment-form').find('.error-container').text(response.message).fadeIn(1000);
            }
        });
    }

    /**
     * Decides what to do next based on the payments response
     */
    function processControllerResponse(response) {
        switch (response.action) {
            case 'error':
                // show error message
                $('.adyen-payment-form').find('.error-container').text(response.message).fadeIn(1000);
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
                    !!response.redirectMethod &&
                    !!response.adyenMerchantReference
                ) {
                    //populate hidden form inputs
                    $('input[name=redirectMethod]').attr('value', response.redirectMethod);
                    $('input[name=issuerUrl]').attr('value', response.issuerUrl);
                    $('input[name=paRequest]').attr('value', response.paRequest);
                    $('input[name=md]').attr('value', response.md);
                    $('input[name=adyenMerchantReference]').attr('value', response.adyenMerchantReference);

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
     * @param data
     */
    function processThreeDS2(data) {
        var threeDSProcessUrl = paymentForm.data().threeDsProcessUrl;

        data.isAjax = true;

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
        data = '';
        allValidCard = false;
    }
});
