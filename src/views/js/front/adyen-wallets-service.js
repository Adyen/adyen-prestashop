var AdyenWallets = window.AdyenWallets || {};

(function () {
    'use strict';

    function AdyenWalletsService() {
        let checkoutController = {},
            paymentStarted = false,
            reference = '',
            paymentData = null,
            type = '',
            productData = null;

        function mountElements(getData) {
            if (!document.getElementById('adyen-express-checkout')) {
                return;
            }

            productData = getData;
            let checkoutElements = document.getElementsByClassName("adyen-express-checkout-element");

            if (checkoutElements.length === 0) {
                return;
            }

            $.each(checkoutController, function (type, controller) {
                controller.unmount();
            });

            checkoutController = {};
            if (!verifyIfComponentsShouldBeMounted()) {
                return;
            }

            let checkoutConfigUrl = document.getElementsByClassName('adyen-config-url')[0],
                userLoggedIn = $('[name=adyenLoggedIn]').length;

            for (let i = 0; i < checkoutElements.length; i++) {
                let type = checkoutElements[i].getElementsByClassName('adyen-type')[0].value;

                checkoutController[type] = new AdyenComponents.CheckoutController({
                    "checkoutConfigUrl": checkoutConfigUrl.value + getConfigParams(),
                    "showPayButton": true,
                    "requireAddress": !userLoggedIn,
                    "requireEmail": !userLoggedIn,
                    "sessionStorage": sessionStorage,
                    "onStateChange": (function (type) {
                        return function () {
                            submitOrder(type);
                        }
                    })(type),
                    "onAuthorized": onAuthorized,
                    "onPaymentAuthorized": onPaymentAuthorized,
                    "onAdditionalDetails": onAdditionalDetails,
                    "onShopperDetails": onShopperDetails
                });

                if (type === 'amazonpay' && getData !== undefined) {
                    sessionStorage.amazonPayProductData = getData();
                }

                checkoutController[type].mount(type, checkoutElements[i]);
            }
        }

        function mountAmazon(getData) {
            if (!document.getElementById('adyen-express-checkout')) {
                return;
            }

            let checkoutElements = document.getElementsByClassName("adyen-express-checkout-element");

            if (checkoutElements.length === 0) {
                return;
            }

            let checkoutConfigUrl = document.getElementsByClassName('adyen-config-url')[0];
            productData = getData;
            checkoutController['amazonpay'] = new AdyenComponents.CheckoutController({
                "checkoutConfigUrl": checkoutConfigUrl.value + getConfigParams(),
                "showPayButton": true,
                "sessionStorage": sessionStorage,
                "onStateChange": (function (type) {
                    return function () {
                        submitOrder(type);
                    }
                })('amazonpay'),
                "onAdditionalDetails": onAdditionalDetails,
            });

            let amazonTypeElement = document.getElementById("adyen-express-checkout-amazonpay");

            checkoutController['amazonpay'].mount('amazonpay', amazonTypeElement);
        }

        function verifyIfComponentsShouldBeMounted() {
            let cartDetailedActions = document.querySelector('.checkout.cart-detailed-actions.card-block'),
                cartDetailedActionsLink = cartDetailedActions ? cartDetailedActions.querySelector('a') : null;

            if (cartDetailedActionsLink && (cartDetailedActionsLink.hasAttribute('disabled') ||
                cartDetailedActionsLink.classList.contains('disabled'))) {
                return false;
            }

            let addToCartButton = document.querySelector('.btn.btn-primary.add-to-cart');

            return !(addToCartButton && (addToCartButton.hasAttribute('disabled') ||
                addToCartButton.classList.contains('disabled')));
        }

        function getAjaxUrlParam(parameterName, string) {
            let decodedURL = decodeURIComponent(string),
                queryParams = decodedURL.substring(decodedURL.indexOf('?') + 1),
                variables = queryParams.split('&');

            for (let i = 0; i < variables.length; i++) {
                let queryParameter = variables[i].split('=');

                if (queryParameter[0] === parameterName) {
                    return queryParameter[1] === undefined ? true : queryParameter[1];
                }
            }
        }

        function getConfigParams() {
            let productDetails = document.getElementById('product-details') ?
                JSON.parse(document.getElementById('product-details').dataset.product) : null;
            if (productDetails) {
                let id_product = productDetails.id_product,
                    id_product_attribute = productDetails.id_product_attribute,
                    id_customization = productDetails.id_customization,
                    quantity_wanted = productDetails.quantity_wanted,
                    price_amount = productDetails.price_amount;

                return '?id_product=' + id_product
                    + '&id_product_attribute=' + id_product_attribute
                    + '&id_customization=' + id_customization
                    + '&quantity_wanted=' + quantity_wanted
                    + '&price_amount=' + price_amount;
            }

            return '&send_new_request=1';
        }

        function submitOrder(type) {
            if (!checkoutController[type].getPaymentMethodStateData() ||
                (paymentStarted && (type === 'paywithgoogle' || type === 'googlepay' || type === 'applepay'))) {
                return;
            }

            let state = JSON.parse(checkoutController[type].getPaymentMethodStateData());
            type = state ? state.paymentMethod.type : '';

            if (type === 'paywithgoogle' || type === 'googlepay' || type === 'applepay') {
                paymentStarted = true;
            }

            let data;

            if (productData) {
                data = {
                    "adyen-additional-data": checkoutController[type].getPaymentMethodStateData(),
                    "product": (type === 'amazonpay' && sessionStorage.amazonPayProductData !== undefined)
                        ? sessionStorage.amazonPayProductData : productData,
                    'adyenShippingAddress': $('[name=adyenShippingAddress]').val(),
                    'adyenBillingAddress': $('[name=adyenBillingAddress]').val(),
                    'adyenEmail': $('[name=adyenEmail]').val()
                };
            } else {
                data = {
                    "adyen-additional-data": checkoutController[type].getPaymentMethodStateData(),
                };
            }

            let paymentUrl = document.getElementsByClassName('adyen-action-url')[0];

            $.ajax({
                method: 'POST',
                dataType: 'json',
                url: paymentUrl.value + '?isXHR=1',
                data: data,
                success: function (response) {
                    if (response.nextStepUrl) {
                        window.location.href = response.nextStepUrl;
                        return;
                    }

                    if (!response.action) {
                        window.location.reload();
                        return;
                    }

                    reference = response.reference;
                    paymentData = null;
                    if (response.action.paymentData) {
                        paymentData = response.action.paymentData;
                    }

                    checkoutController[type].handleAction(response.action);
                },
                error: function () {
                    window.location.reload();
                }
            });
        }

        function onAdditionalDetails(additionalData) {
            if (paymentData) {
                additionalData.paymentData = paymentData

                if (additionalData.details.paymentSource === 'paypal') {
                    let shippingAddressInput = $('[name=adyenShippingAddress]'),
                        billingAddressInput = $('[name=adyenBillingAddress]'),
                        emailInput = $('[name=adyenEmail]');

                    additionalData.adyenShippingAddress = shippingAddressInput.val();
                    additionalData.adyenBillingAddress = billingAddressInput.val();
                    additionalData.adyenEmail = emailInput.val();
                }
            }

            type = type !== '' ? type : additionalData.details.paymentSource;
            let additionalDataUrl = document.getElementsByClassName('adyen-redirect-action-url')[0];

            $.ajax({
                method: 'POST',
                dataType: 'json',
                url: additionalDataUrl.value + "?adyenMerchantReference=" + reference + '&adyenPaymentType=' + type + '&isXHR=1',
                data: additionalData,
                success: function (response) {
                    window.location.href = response.nextStepUrl;
                },
                error: function () {
                    window.location.reload();
                }
            });
        }

        function onAuthorized (paymentData) {
        }

        function onPaymentAuthorized(paymentData) {
            let me = this;

            return new Promise(function (resolve, reject) {
                let shippingAddressInput = $('[name=adyenShippingAddress]'),
                    billingAddressInput = $('[name=adyenBillingAddress]'),
                    emailInput = $('[name=adyenEmail]');

                let shippingAddress = {
                    firstName: paymentData.shippingAddress.name,
                    lastName: '',
                    street: paymentData.shippingAddress.address1,
                    zipCode: paymentData.shippingAddress.postalCode,
                    state: paymentData.shippingAddress.administrativeArea,
                    city: paymentData.shippingAddress.locality,
                    country: paymentData.shippingAddress.countryCode,
                    phone: paymentData.shippingAddress.phoneNumber
                };

                shippingAddressInput.val(JSON.stringify(shippingAddress));
                billingAddressInput.val(JSON.stringify(shippingAddress));
                emailInput.val(JSON.stringify(paymentData.email));

                resolve({transactionState: 'SUCCESS'});
            });
        }

        function onShopperDetails (shopperDetails, rawData, actions) {
            let shippingAddressInput = $('[name=adyenShippingAddress]'),
                billingAddressInput = $('[name=adyenBillingAddress]'),
                emailInput = $('[name=adyenEmail]'),
                shippingAddress = {
                    firstName: shopperDetails.shopperName.firstName,
                    lastName: shopperDetails.shopperName.lastName,
                    street: shopperDetails.shippingAddress.street,
                    zipCode: shopperDetails.shippingAddress.postalCode,
                    city: shopperDetails.shippingAddress.city,
                    country: shopperDetails.shippingAddress.country,
                    phone: shopperDetails.telephoneNumber
                },
                billingAddress = {
                    firstName: shopperDetails.shopperName.firstName,
                    lastName: shopperDetails.shopperName.lastName,
                    street: shopperDetails.billingAddress.street,
                    zipCode: shopperDetails.billingAddress.postalCode,
                    city: shopperDetails.billingAddress.city,
                    country: shopperDetails.billingAddress.country,
                    phone: shopperDetails.telephoneNumber
                };

            shippingAddressInput.val(JSON.stringify(shippingAddress));
            billingAddressInput.val(JSON.stringify(billingAddress));
            emailInput.val(JSON.stringify(shopperDetails.shopperEmail));

            actions.resolve();
        }

        this.mountElements = mountElements;
        this.mountAmazon = mountAmazon;
        this.getAjaxUrlParam = getAjaxUrlParam;
    }

    AdyenWallets.AdyenWalletsService = AdyenWalletsService;
})();
