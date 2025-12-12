;var AdyenComponents = window.AdyenComponents || {};
(function () {
    'use strict';

    function CheckoutConfigProvider() {
        let configCache = {};

        this.getConfiguration = async (configUrl) => {
            if (configCache[configUrl]) {
                return configCache[configUrl];
            }

            configCache[configUrl] = new Promise(async (resolve, reject) => {
                const urlParams = new URLSearchParams(window.location.search);
                const idCart = urlParams.get('id_cart');
                const idOrder = urlParams.get('id_order');
                const key = urlParams.get('key');
                const isThankYouPage = Boolean(idCart && idOrder && key);
                let fullConfigUrl = configUrl;

                if (isThankYouPage) {
                    const paramsToAdd = [];

                    if (idCart) paramsToAdd.push(`id_cart=${encodeURIComponent(idCart)}`);
                    if (idOrder) paramsToAdd.push(`id_order=${encodeURIComponent(idOrder)}`);
                    if (key) paramsToAdd.push(`key=${encodeURIComponent(key)}`);

                    if (paramsToAdd.length > 0) {
                        const separator = configUrl.includes('?') ? '&' : '?';
                        fullConfigUrl = `${configUrl}${separator}${paramsToAdd.join('&')}`;
                    }
                }

                let checkoutConfig = await (await fetch(fullConfigUrl, {
                    method: "GET"
                })).json();

                if (checkoutConfig.errorCode) {
                    reject('Checkout configuration error');
                }

                return resolve(checkoutConfig);
            });

            return configCache[configUrl];
        };
    }

    AdyenComponents.CheckoutConfigProvider = new CheckoutConfigProvider();
})();

(function () {
    'use strict';
    // Use for local testing only, Amazon pay requires globally accessible URL
    const devOnlyConfig = {
        localShopDomain: '',
        globalReplacementDomain: ''
    };

    const wallets = ['applepay', 'amazonpay', 'paywithgoogle', 'googlepay', 'paypal'],
        giftCards = [
            'auriga', 'babygiftcard', 'bloemengiftcard', 'cashcomgiftcard', 'eagleeye_voucher', 'entercard',
            'expertgiftcard', 'fashioncheque', 'fijncadeau', 'valuelink', 'fleuropbloemenbon', 'fonqgiftcard',
            'gallgall', 'givex', 'hallmarkcard', 'igive', 'ikano', 'kadowereld', 'kidscadeau', 'kindpas',
            'leisurecard', 'nationalebioscoopbon', 'netscard', 'oberthur', 'pathegiftcard', 'payex', 'podiumcard',
            'resursgiftcard', 'rotterdampas', 'genericgiftcard', 'schoolspullenpas', 'sparnord', 'sparebank',
            'svs', 'universalgiftcard', 'vvvcadeaubon', 'vvvgiftcard', 'webshopgiftcard', 'winkelcheque',
            'winterkledingpas', 'xponcard', 'yourgift', 'prosodie_illicado'
        ];

    /**
     * Handles Adyen web components mounting and session data managing.
     *
     * @constructor
     *
     * @param {{
     * checkoutConfigUrl: string,
     * showPayButton: boolean,
     * requireAddress: boolean,
     * requireEmail: boolean,
     * sessionStorage: sessionStorage,
     * onStateChange: function|undefined,
     * onAdditionalDetails: function|undefined,
     * onAuthorized: function|undefined,
     * onPaymentAuthorized: function|undefined,
     * onApplePayPaymentAuthorized: function|undefined,
     * onShippingContactSelected: function|undefined,
     * onPaymentDataChanged: function|undefined,
     * onShopperDetails: function|undefined
     * onPayButtonClick: function|undefined,
     * onClickToPay: function|undefined,
     * onShippingAddressChanged: function|undefined,
     * balanceCheck: function|undefined,
     * saveStateDataUrl: string|undefined,
     * getStateDataUrl: string|undefined
     * }} config
     */
    function CheckoutController(config) {
        const url = new URL(location.href);
        let clickToPayHandled = false;

        if (url.hostname === devOnlyConfig.localShopDomain && devOnlyConfig.globalReplacementDomain) {
            url.hostname = devOnlyConfig.globalReplacementDomain;
            url.protocol = 'https:';
        }

        config.requireAddress = config.requireAddress || false;

        config.requireEmail = config.requireEmail || false;

        config.onStateChange = config.onStateChange || function () {
        };
        config.onAdditionalDetails = config.onAdditionalDetails || function () {
        };
        config.onPaymentDataChanged = config.onPaymentDataChanged || function () {
            return new Promise(async resolve => {
                resolve({});
            });
        };
        config.onAuthorized = config.onAuthorized || function () {
        };
        config.onPaymentAuthorized = config.onPaymentAuthorized || function () {
            return new Promise(function (resolve, reject) {
                resolve({transactionState: 'SUCCESS'});
            });
        };
        config.onApplePayPaymentAuthorized = config.onApplePayPaymentAuthorized || function (resolve, reject, event) {
            resolve(window.ApplePaySession.STATUS_SUCCESS);
        };
        config.onPayButtonClick = config.onPayButtonClick || function (resolve, reject) {
            resolve();
        };
        config.onClickToPay = config.onClickToPay || function () {
        };
        config.balanceCheck = config.balanceCheck || function (resolve, reject, data) {
        };

        const handleOnClick = (resolve, reject) => {
            return config.onPayButtonClick(resolve, reject);
        };

        /* GooglePay callbacks */
        const handleAuthorized = (paymentData) => {
            return config.onAuthorized(paymentData);
        }

        const handlePaymentDataChanged = (intermediatePaymentData) => {
            return config.onPaymentDataChanged(intermediatePaymentData);
        };

        const handlePaymentAuthorized = (paymentData) => {
            return config.onPaymentAuthorized(paymentData);
        }

        const handleApplePayPaymentAuthorized = (resolve, reject, event) => {
            return config.onApplePayPaymentAuthorized(resolve, reject, event);
        }

        const handleOnShippingContactSelected = (resolve, reject, event) => {
            return config.onShippingContactSelected(resolve, reject, event);
        }

        const shippingAddressChanged = (data, actions, component) => {
            return config.onShippingAddressChanged(data, actions, component);
        }

        let checkout,
            activeComponent,
            isStateValid = true,
            sessionStorage = config.sessionStorage || window.sessionStorage,
            amazonCheckoutSessionId = url.searchParams.get('amazonCheckoutSessionId');

        let googlePaymentDataCallbacks = {};
        if (config.requireAddress) {
            googlePaymentDataCallbacks = {
                onPaymentDataChanged: handlePaymentDataChanged,
                onPaymentAuthorized: handlePaymentAuthorized,
            };
        }

        let paymentMethodSpecificConfig = {
            "amazonpay": {
                "productType": 'PayOnly',
                "checkoutMode": 'ProcessOrder',
                "chargePermissionType": 'OneTime',
                "onClick": handleOnClick,
                "returnUrl": url.href,
                "cancelUrl": url.href
            },
            "paywithgoogle": {
                onClick: handleOnClick,
                isExpress: true,
                callbackIntents: config.requireAddress ? ['SHIPPING_ADDRESS', 'PAYMENT_AUTHORIZATION'] : [],
                shippingAddressRequired: config.requireAddress,
                emailRequired: config.requireEmail,
                shippingAddressParameters: {
                    allowedCountryCodes: [],
                    phoneNumberRequired: true
                },
                shippingOptionRequired: false,
                buttonSizeMode: "fill",
                onAuthorized: handleAuthorized,
                paymentDataCallbacks: googlePaymentDataCallbacks
            },
            "googlepay": {
                onClick: handleOnClick,
                isExpress: true,
                callbackIntents: config.requireAddress ? ['SHIPPING_ADDRESS', 'PAYMENT_AUTHORIZATION'] : [],
                shippingAddressRequired: config.requireAddress,
                emailRequired: config.requireEmail,
                shippingAddressParameters: {
                    allowedCountryCodes: [],
                    phoneNumberRequired: true
                },
                shippingOptionRequired: false,
                buttonSizeMode: "fill",
                onAuthorized: handleAuthorized,
                paymentDataCallbacks: googlePaymentDataCallbacks
            },
            "paypal": {
                blockPayPalCreditButton: true,
                blockPayPalPayLaterButton: true,
                onClick: (source, event, self) => {
                    return handleOnClick(event.resolve, event.reject);
                }
            },
            "giftcard": {
                type: 'giftcard',
                showPayButton: true,
                onBalanceCheck: function (resolve, reject, data) {
                    config.balanceCheck(resolve, reject, data);
                }
            }
        };

        if (config.requireAddress) {
            paymentMethodSpecificConfig.applepay = {
                isExpress: true,
                requiredBillingContactFields: ['postalAddress'],
                requiredShippingContactFields: ['postalAddress', 'name', 'phoneticName', 'phone', 'email'],
                onAuthorized: handleApplePayPaymentAuthorized,
            }

            if (config.onShippingContactSelected) {
                paymentMethodSpecificConfig.applepay.onShippingContactSelected = handleOnShippingContactSelected
            }
        }

        if (config.amount) {
            paymentMethodSpecificConfig['amazonpay']['amount'] = config.amount;
            paymentMethodSpecificConfig['amazonpay']['currency'] = config.amount.currency;

            paymentMethodSpecificConfig['paypal']['amount'] = config.amount;
        }

        if (amazonCheckoutSessionId) {
            paymentMethodSpecificConfig['amazonpay']['amazonCheckoutSessionId'] = amazonCheckoutSessionId;
            paymentMethodSpecificConfig['amazonpay']['showOrderButton'] = false;
        }

        /**
         *
         * @returns {Promise<AdyenCheckout>}
         */
        const getCheckoutInstance = async () => {
            if (!checkout) {
                let checkoutConfig = await AdyenComponents.CheckoutConfigProvider.getConfiguration(config.checkoutConfigUrl);

                checkoutConfig.onChange = handleOnChange;
                checkoutConfig.onSubmit = handleOnChange;
                checkoutConfig.onAdditionalDetails = handleAdditionalDetails;
                checkoutConfig.onAuthorized = handleAuthorized;
                checkoutConfig.onPaymentDataChanged = handlePaymentDataChanged;
                checkoutConfig.onPaymentAuthorized = handlePaymentAuthorized;
                checkoutConfig.onApplePayPaymentAuthorized = handleApplePayPaymentAuthorized;
                if (config.onShippingContactSelected) {
                    checkoutConfig.onShippingContactSelected = handleOnShippingContactSelected;
                }

                if (config.showPayButton) {
                    checkoutConfig.showPayButton = true;
                } else {
                    checkoutConfig.showPayButton = false;
                }

                checkout = await AdyenCheckout(checkoutConfig);
            }

            return Promise.resolve(checkout);
        };

        const handleOnChange = (state) => {
            isStateValid = state.isValid;

            if (isStateValid) {
                saveStateData(state.data).then(response => {
                    if (isStateValid && !clickToPayHandled && isClickToPayPaymentMethod(state.data.paymentMethod)) {
                        clickToPayHandled = true;
                        config.onClickToPay();
                    }

                    config.onStateChange();
                });

                return;
            }

            config.onStateChange();
        };

        const saveStateData = (stateData) => {
            let payload = {
                stateData: JSON.stringify(stateData)
            };
            return fetch(config.saveStateDataUrl, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
        }

        const saveGiftCardStateData = (stateData) => {
            let payload = {
                giftCardsData: JSON.stringify(stateData)
            };
            return fetch(config.saveStateDataUrl, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
        }

        /**
         * Returns stringify version of gift card state data
         *
         * @returns {string}
         */
        const getGiftCardStateData = async () => {
            const params = new URLSearchParams({
                key: 'giftCardsData'
            });
            const getStateDataUrl = config.getStateDataUrl + '&' + params.toString();

            return await fetch(getStateDataUrl).then(response => response.json());
        };

        /**
         * Returns true if Click to Pay is selected.
         *
         * @returns {boolean}
         */
        const isClickToPayPaymentMethod = (paymentMethod) => {
            return paymentMethod.type === 'scheme' && !paymentMethod.hasOwnProperty('encryptedSecurityCode');
        };

        const handleAdditionalDetails = (state) => {
            config.onAdditionalDetails(state.data);
        };

        /**
         * Mounts adyen web component for a given type under the mount element.
         *
         * @param paymentType string Web component payment method type
         * @param mountElement string|HTMLElement Dom elemnt or selector for dom element
         * @param storedPaymentMethodId string Optional stored payment method id to render component for
         */
        const mount = (paymentType, mountElement, storedPaymentMethodId) => {
            isStateValid = true;

            getCheckoutInstance().then((checkoutInstance) => {
                unmount();

                // Do not mount unavailable payment method
                if (!findPaymentMethodConfig(checkoutInstance, paymentType)) {
                    return;
                }

                sessionStorage.setItem('adyen-needs-sate-data-reinit', 'false');

                if (!config.showPayButton && wallets.includes(paymentType)) {
                    return;
                }

                if (storedPaymentMethodId && paymentType !== 'scheme') {
                    config.onStateChange();

                    return;
                }

                let paymentMethodConfig = findSpecificPaymentMethodConfig(paymentType) ||
                    findStoredPaymentMethodConfig(checkoutInstance, storedPaymentMethodId);

                // Configuration on the checkout instance level does not work for amazonpay, copy it on component level
                if ('amazonpay' === paymentType && checkoutInstance.options.paymentMethodsConfiguration[paymentType]) {
                    paymentMethodConfig['configuration'] = checkoutInstance.options.paymentMethodsConfiguration[paymentType].configuration;
                }

                // If there is applepay specific configuration then set country code to configuration
                if ('applepay' === paymentType &&
                    paymentMethodConfig) {
                    paymentMethodConfig.countryCode = checkoutInstance.options.countryCode;
                }

                activeComponent = checkoutInstance.create(
                    giftCards.includes(paymentType) ? 'giftcard' : paymentType,
                    paymentMethodConfig
                ).mount(mountElement);

                isStateValid = !!activeComponent.isValid && activeComponent.isValid;

                config.onStateChange();

                if (amazonCheckoutSessionId) {
                    activeComponent.submit();
                }
            });
        };

        const handleAdditionalAction = (action, mountElement) => {
            getCheckoutInstance().then((checkoutInstance) => {
                unmount();

                checkoutInstance.createFromAction(action).mount(mountElement);
            });
        };

        const handleAction = (action) => {
            activeComponent.handleAction(action);
        }

        /**
         * Unmounts the active web component (f there is one) and resets the payment method state
         */
        const unmount = () => {
            isStateValid = true;
            forceFetchingComponentStateData();


            if (activeComponent && checkout) {
                checkout.remove(activeComponent);
                activeComponent = null;
            }

            config.onStateChange();
        };

        /**
         * Checks if payment method state is valid for currently mounted component
         *
         * @returns {boolean}
         */
        const isPaymentMethodStateValid = () => {
            return isStateValid;
        };

        /**
         * Returns stringify version of payment method state data
         *
         * @returns {string}
         */
        const getPaymentMethodStateData = async () => {
            const params = new URLSearchParams({
                key: 'stateData'
            });
            const getStateDataUrl = config.getStateDataUrl + '&' + params.toString();

            return await fetch(getStateDataUrl).then(response => response.json());
        };

        /**
         * Returns true if Adyen web component was never mounted and therefore the initial payment state data collection
         * is required.
         *
         * @returns {boolean}
         */
        const isPaymentMethodStateReinitializationRequired = () => {
            return 'true' === sessionStorage.getItem('adyen-needs-sate-data-reinit');
        };

        /**
         * Forces the validation errors to appear in currently mounted component
         */
        const showValidation = () => {
            if (activeComponent && 'showValidation' in activeComponent) {
                activeComponent.showValidation();
            }
        };

        const forceFetchingComponentStateData = () => {
            sessionStorage.setItem('adyen-needs-sate-data-reinit', 'true');
        }

        const findSpecificPaymentMethodConfig = (paymentType) => {
            if (giftCards.includes(paymentType)) {
                let result = paymentMethodSpecificConfig['giftcard'];
                result['brand'] = paymentType;

                return result;
            }

            return paymentMethodSpecificConfig[paymentType] || null;
        };

        const findStoredPaymentMethodConfig = (checkoutInstance, storedPaymentMethodId) => {
            if (!storedPaymentMethodId) {
                return null;
            }

            for (const paymentMethod of checkoutInstance.options.paymentMethodsResponse.storedPaymentMethods) {
                if (paymentMethod.id === storedPaymentMethodId) {
                    return {
                        ...paymentMethod,
                        storedPaymentMethodId
                    };
                }
            }

            return null;
        };

        const findPaymentMethodConfig = (checkoutInstance, paymentMethodType) => {
            if (!paymentMethodType) {
                return null;
            }

            let isGiftCard = giftCards.includes(paymentMethodType);

            for (const paymentMethod of checkoutInstance.options.paymentMethodsResponse.paymentMethods) {
                if (paymentMethod.type === paymentMethodType) {
                    return paymentMethod;
                }

                if (isGiftCard && paymentMethod.brand === paymentMethodType) {
                    return paymentMethod;
                }

                if (paymentMethodType === 'googlepay' && paymentMethod.type === 'paywithgoogle') {
                    return paymentMethod;
                }

                if (paymentMethodType === 'paywithgoogle' && paymentMethod.type === 'googlepay') {
                    return paymentMethod;
                }
            }

            return null;
        };

        this.mount = mount;
        this.handleAdditionalAction = handleAdditionalAction;
        this.handleAction = handleAction;
        this.unmount = unmount;
        this.getPaymentMethodStateData = getPaymentMethodStateData;
        this.isPaymentMethodStateReinitializationRequired = isPaymentMethodStateReinitializationRequired;
        this.isPaymentMethodStateValid = isPaymentMethodStateValid;
        this.showValidation = showValidation;
        this.forceFetchingComponentStateData = forceFetchingComponentStateData;
        this.saveGiftCardStateData = saveGiftCardStateData;
        this.getGiftCardStateData = getGiftCardStateData;
    }

    AdyenComponents.CheckoutController = CheckoutController;
})();
