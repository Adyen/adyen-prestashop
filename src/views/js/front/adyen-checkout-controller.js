;var AdyenComponents = window.AdyenComponents || {};
(function () {
    'use strict';

    function CheckoutConfigProvider() {
        let configCache = {};

        this.getConfiguration = async (configUrl) => {
            if (configCache[configUrl] && !configUrl.includes("send_new_request")) {
                return configCache[configUrl];
            }

            configCache[configUrl] = new Promise(async (resolve, reject) => {
                let checkoutConfig = await (await fetch(configUrl, {
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
     * sessionStorage: sessionStorage,
     * onStateChange: function|undefined,
     * onAdditionalDetails: function|undefined,
     * onPayButtonClick: function|undefined,
     * }} config
     */
    function CheckoutController(config) {
        const url = new URL(location.href);
        if (url.hostname === devOnlyConfig.localShopDomain && devOnlyConfig.globalReplacementDomain) {
            url.hostname = devOnlyConfig.globalReplacementDomain;
            url.protocol = 'https:';
        }

        config.onStateChange = config.onStateChange || function () {
        };
        config.onAdditionalDetails = config.onAdditionalDetails || function () {
        };
        config.onPayButtonClick = config.onPayButtonClick || function (resolve, reject) {
            resolve();
        };

        const handleOnClick = (resolve, reject) => {
            return config.onPayButtonClick(resolve, reject);
        };

        let checkout,
            activeComponent,
            isStateValid = true,
            sessionStorage = config.sessionStorage || window.sessionStorage,
            amazonCheckoutSessionId = url.searchParams.get('amazonCheckoutSessionId'),
            paymentMethodSpecificConfig = {
                "amazonpay": {
                    "productType": 'PayOnly',
                    "checkoutMode": 'ProcessOrder',
                    "chargePermissionType": 'OneTime',
                    "onClick": handleOnClick,
                    "returnUrl": url.href,
                    "cancelUrl": url.href
                },
                "paywithgoogle": {"onClick": handleOnClick, "buttonSizeMode": "fill"},
                "googlepay": {"onClick": handleOnClick, "buttonSizeMode": "fill"},
                "paypal": {
                    "blockPayPalCreditButton": true,
                    "blockPayPalPayLaterButton": true,
                    "onClick": (source, event, self) => {
                        return handleOnClick(event.resolve, event.reject);
                    }
                }
            };

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
                sessionStorage.setItem('adyen-payment-method-state-data', JSON.stringify(state.data));
            }

            config.onStateChange();
        };

        const handleAdditionalDetails = (state) => {
            config.onAdditionalDetails(state.data);
        };

        /**
         * Mounts adyen web component for a given type under the mount element.
         *
         * @param paymentType: string Web component payment method type
         * @param mountElement: string|HTMLElement Dom elemnt or selector for dom element
         * @param storedPaymentMethodId: string Optional stored payment method id to render component for
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
                    sessionStorage.setItem('adyen-payment-method-state-data', JSON.stringify({
                        'paymentMethod': {
                            'type': paymentType,
                            'storedPaymentMethodId': storedPaymentMethodId
                        },
                    }));

                    config.onStateChange();

                    return;
                }

                let paymentMethodConfig = findSpecificPaymentMethodConfig(paymentType) ||
                    findStoredPaymentMethodConfig(checkoutInstance, storedPaymentMethodId, paymentType);

                // Configuration on the checkout instance level does not work for amazonpay, copy it on component level
                if ('amazonpay' === paymentType && checkoutInstance.options.paymentMethodsConfiguration[paymentType]) {
                    paymentMethodConfig['configuration'] = checkoutInstance.options.paymentMethodsConfiguration[paymentType].configuration;
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
            sessionStorage.removeItem('adyen-payment-method-state-data');
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
        const getPaymentMethodStateData = () => {
            return sessionStorage.getItem('adyen-payment-method-state-data');
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
                return {
                    type: 'giftcard',
                    brand: paymentType
                };
            }

            return paymentMethodSpecificConfig[paymentType] || null;
        };

        const findStoredPaymentMethodConfig = (checkoutInstance, storedPaymentMethodId, paymentType) => {
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
    }

    AdyenComponents.CheckoutController = CheckoutController;
})();
