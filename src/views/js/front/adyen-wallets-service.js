var AdyenWallets = window.AdyenWallets || {};

(function () {
    'use strict';

    function AdyenWalletsService() {
        let checkoutController = {},
            paymentStarted = false,
            pspReference = '',
            reference = '',
            paymentData = null,
            type = '',
            productData = null,
            countryCode = '';

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
                    "onPaymentDataChanged": onPaymentDataChanged,
                    "onApplePayPaymentAuthorized": onApplePayPaymentAuthorized,
                    "onShippingContactSelected": onShippingContactSelected,
                    "onAdditionalDetails": onAdditionalDetails,
                    "onShopperDetails": onShopperDetails,
                    "onShippingAddressChanged": onShippingAddressChanged
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

            return '';
        }

        function submitOrder(type) {
            if (!checkoutController[type].getPaymentMethodStateData() ||
                (paymentStarted && (type === 'paywithgoogle' || type === 'googlepay' || type === 'applepay'))) {
                return;
            }

            let state = JSON.parse(checkoutController[type].getPaymentMethodStateData());
            type = state ? state.paymentMethod.type : '';

            let shippingAddress = $('[name=adyenShippingAddress]').val();
            let billingAddress = $('[name=adyenBillingAddress]').val();
            let email = $('[name=adyenEmail]').val();
            let userLoggedIn = $('[name=adyenLoggedIn]').length;

            if (type === 'applepay' && !userLoggedIn) {
                return;
            }

            if (type === 'paywithgoogle' || type === 'googlepay' || type === 'applepay') {
                paymentStarted = true;
            }

            let data;

            if (productData) {
                data = {
                    "adyen-additional-data": checkoutController[type].getPaymentMethodStateData(),
                    "product": (type === 'amazonpay' && sessionStorage.amazonPayProductData !== undefined)
                        ? sessionStorage.amazonPayProductData : productData,
                    'adyenShippingAddress': shippingAddress,
                    'adyenBillingAddress': billingAddress,
                    'adyenEmail': email
                };
            } else {
                data = {
                    "adyen-additional-data": checkoutController[type].getPaymentMethodStateData(),
                    'adyenShippingAddress': shippingAddress,
                    'adyenBillingAddress': billingAddress,
                    'adyenEmail': email
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

                    if (response.pspReference) {
                        pspReference = response.pspReference;
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

        function onAuthorized(paymentData) {
        }

        function onPaymentAuthorized(paymentData) {
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

        function onPaymentDataChanged(intermediatePaymentData) {
            return new Promise(async resolve => {
                const {shippingAddress} = intermediatePaymentData;
                const paymentDataRequestUpdate = {};
                let amount = 0,
                    data = {};

                let shippingAddressData = {
                    zipCode: shippingAddress.postalCode,
                    city: shippingAddress.locality,
                    country: shippingAddress.countryCode,
                    state: shippingAddress.administrativeArea,
                    firstName: 'Temp',
                    lastName: 'Temp',
                    street: 'Street 123'
                };

                data = {
                    'newAddress': {
                        'adyenShippingAddress': JSON.stringify(shippingAddressData),
                        'adyenBillingAddress': JSON.stringify(shippingAddressData),
                    }
                }

                let configUrlInput = document.getElementsByClassName('adyen-config-url')[0],
                    checkoutConfigUrl = configUrlInput.value + getConfigParams();


                $.ajax({
                    type: "POST",
                    url: checkoutConfigUrl + '/isXHR/1',
                    data: data,
                    success: function (response) {
                        amount = parseInt(response.amount) / 100;

                        if (countryCode === '') {
                            countryCode = response.country;
                        }

                        paymentDataRequestUpdate.newTransactionInfo = {
                            currencyCode: response.currency,
                            totalPriceStatus: "FINAL",
                            totalPrice: (amount).toString(),
                            totalPriceLabel: "Total",
                            countryCode: countryCode,
                        };
                        resolve(paymentDataRequestUpdate);
                    },
                    error: function (response) {
                        paymentDataRequestUpdate.error = {
                            reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
                            message: response.message ?? "Cannot ship to the selected address",
                            intent: "SHIPPING_ADDRESS"
                        };
                        resolve(paymentDataRequestUpdate);
                    }
                });
            });
        }

        function onApplePayPaymentAuthorized(resolve, reject, event) {
            let shippingContact = event.payment.shippingContact;
            let shippingAddress = {
                firstName: shippingContact.givenName,
                lastName: shippingContact.familyName,
                street: shippingContact.addressLines.length > 0 ? shippingContact.addressLines[0] : '',
                city: shippingContact.locality,
                state: shippingContact.administrativeArea,
                country: shippingContact.countryCode,
                zipCode: shippingContact.postalCode,
                phone: shippingContact.phoneNumber,
            };

            let type = 'applepay';

            if (!checkoutController[type].getPaymentMethodStateData() || paymentStarted) {
                return;
            }

            paymentStarted = true;
            let data;

            if (productData) {
                data = {
                    "adyen-additional-data": checkoutController[type].getPaymentMethodStateData(),
                    "product": productData,
                    'adyenShippingAddress': JSON.stringify(shippingAddress),
                    'adyenBillingAddress': JSON.stringify(shippingAddress),
                    'adyenEmail': JSON.stringify(shippingContact.emailAddress)
                };
            } else {
                data = {
                    "adyen-additional-data": checkoutController[type].getPaymentMethodStateData(),
                    'adyenShippingAddress': JSON.stringify(shippingAddress),
                    'adyenBillingAddress': JSON.stringify(shippingAddress),
                    'adyenEmail': JSON.stringify(shippingContact.emailAddress)
                };
            }

            let paymentUrl = document.getElementsByClassName('adyen-action-url')[0];

            $.ajax({
                method: 'POST',
                dataType: 'json',
                url: paymentUrl.value + '?isXHR=1',
                data: data,
                success: function (response) {
                    if (response.nextStepUrl.includes('order-confirmation')) {
                        resolve(window.ApplePaySession.STATUS_SUCCESS);
                        window.location.href = response.nextStepUrl;
                    } else {
                        reject(window.ApplePaySession.STATUS_FAILURE);
                        window.location.href = response.nextStepUrl;
                    }
                },
                error: function () {
                    reject(window.ApplePaySession.STATUS_FAILURE);
                    window.location.reload();
                }
            });
        }

        function onShippingContactSelected(resolve, reject, event) {
            let address = event.shippingContact;
            let data = {};
            let amount = 0;

            let shippingAddress = {
                firstName: 'Temp',
                lastName: 'Temp',
                street: 'Street 123',
                city: address.locality,
                state: address.administrativeArea,
                country: address.countryCode,
                zipCode: address.postalCode,
                phone: '',
            };

            data = {
                'newAddress': {
                    'adyenShippingAddress': JSON.stringify(shippingAddress),
                    'adyenBillingAddress': JSON.stringify(shippingAddress),
                }
            }

            let configUrlInput = document.getElementsByClassName('adyen-config-url')[0],
                checkoutConfigUrl = configUrlInput.value + getConfigParams();

            $.ajax({
                method: 'POST',
                dataType: 'json',
                url: checkoutConfigUrl + '&isXHR=1',
                data: data,
                success: function (response) {
                    amount = parseInt(response.amount) / 100;
                    let applePayShippingMethodUpdate = {};

                    applePayShippingMethodUpdate.newTotal = {
                        type: 'final',
                        label: 'Total amount',
                        amount: (amount).toString()
                    };

                    resolve(applePayShippingMethodUpdate);
                },
                error: function (response) {
                    let update = {
                        newTotal: {
                            type: 'final',
                            label: 'Total amount',
                            amount: (amount).toString()
                        },
                        errors: [new ApplePayError(
                            'shippingContactInvalid',
                            'countryCode',
                            response.message)
                        ]
                    };
                    resolve(update);
                }
            });
        }

        function onShopperDetails(shopperDetails, rawData, actions) {
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

        function onShippingAddressChanged(data, actions, component) {
            let updateUrl = document.getElementsByClassName('adyen-paypal-update-order-url')[0];

            $.ajax({
                type: "POST",
                url: updateUrl.value + getConfigParams() + '&isXHR=1',
                data: {
                    shippingAddress: data.shippingAddress,
                    paymentData: component.paymentData,
                    pspReference: pspReference
                },
                success: function (response) {
                    component.updatePaymentData(response.paymentData);
                    actions.resolve();
                },
                error: function (response) {
                    actions.reject(new Error('fail'));
                }
            });
        }

        this.mountElements = mountElements;
        this.mountAmazon = mountAmazon;
        this.getAjaxUrlParam = getAjaxUrlParam;
    }

    AdyenWallets.AdyenWalletsService = AdyenWalletsService;
})();
