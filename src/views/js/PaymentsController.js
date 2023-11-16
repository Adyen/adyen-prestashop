if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * @typedef PaymentMethod
     * @property {string} methodId
     * @property {string} name
     * @property {string} code
     * @property {string?} methodName
     * @property {string} logo
     * @property {'creditOrDebitCard' | 'buyNowPayLater' | 'cashOrAtm' | 'directDebit' | 'onlinePayments' | 'wallet' |
     *     'prepaidAndGiftCard' | 'mobile'} paymentType
     * @property {boolean} status
     * @property {string[]} currencies
     * @property {string[]} countries
     */

    /**
     * Map between a method code and a method identifier on Adyen
     *
     * @type {Record<string, string>}
     */
    const methodMap = {
        ach: 'ach-direct-debit',
        alipay: 'alipay',
        amazonpay: 'amazon-pay',
        applepay: 'apple-pay',
        blik: 'blik',
        directEbanking: 'cards',
        ebanking_FI: 'finland-online-banking',
        eps: 'eps',
        giropay: 'giropay',
        googlepay: 'google-pay',
        ideal: 'ideal',
        klarna: 'klarna',
        klarna_account: 'klarna',
        klarna_paynow: 'klarna',
        mbway: 'mb-way',
        mobilepay: 'mobilepay',
        momo_wallet: 'momo-wallet',
        multibanco: 'multibanco',
        molpay_ebanking_TH: 'online-banking-thailand',
        oney: 'oney',
        onlineBanking_PL: 'online-banking-poland',
        paypal: 'paypal',
        paysafecard: 'paysafecard',
        paywithgoogle: 'google-pay',
        ratepay: 'ratepay',
        ratepay_directdebit: 'ratepay',
        scheme: 'cards',
        sepadirectdebit: 'sepa-direct-debit',
        swish: 'swish',
        trustly: 'trustly',
        twint: 'twint',
        vipps: 'vipps'
    };

    const methodTypes = [
        'creditOrDebitCard',
        'buyNowPayLater',
        'cashOrAtm',
        'directDebit',
        'onlinePayments',
        'wallet',
        'prepaidAndGiftCard',
        'mobile'
    ];

    const currencies = [
        'AED',
        'AUD',
        'BGN',
        'BHD',
        'BRL',
        'CAD',
        'CHF',
        'CNY',
        'CZK',
        'DKK',
        'EUR',
        'GBP',
        'HKD',
        'HUF',
        'ISK',
        'ILS',
        'INR',
        'JOD',
        'JPY',
        'KRW',
        'KWD',
        'MYR',
        'NOK',
        'NZD',
        'OMR',
        'PLN',
        'QAR',
        'RON',
        'RUB',
        'SAR',
        'SEK',
        'SGD',
        'THB',
        'TWD',
        'USD',
        'ZAR'
    ];

    const countries = [
        'AU',
        'AT',
        'BE',
        'BG',
        'CA',
        'HR',
        'CY',
        'CZ',
        'DK',
        'EE',
        'FI',
        'FR',
        'DE',
        'GI',
        'GR',
        'HK',
        'HU',
        'IS',
        'IE',
        'IT',
        'JP',
        'LV',
        'LI',
        'LT',
        'LU',
        'MT',
        'NL',
        'NZ',
        'NO',
        'PL',
        'PT',
        'PR',
        'RO',
        'SG',
        'SK',
        'SI',
        'ES',
        'SE',
        'CH',
        'AE',
        'GB',
        'US'
    ];

    /**
     * @typedef AdditionalDataConfig
     * @property {boolean?} showLogos
     * @property {boolean?} singleClickPayment
     * @property {boolean?} sendBasket
     * @property {boolean?} installments
     * @property {boolean?} installmentAmounts
     * @property {string[]?} installmentCountries
     * @property {string?} supportedInstallments
     * @property {number?} minimumAmount
     * @property {string?} numberOfInstallments
     * @property {string?} bankIssuer
     * @property {string?} merchantId
     * @property {string?} publicKeyId
     * @property {string?} storeId
     * @property {string?} gatewayMerchantId
     * @property {string?} merchantName
     * @property {boolean?} displayButtonOn
     */

    /**
     * @typedef PaymentMethodConfiguration
     * @property {boolean} isNew
     * @property {string} methodId
     * @property {string} code
     * @property {string?} name
     * @property {string?} description
     * @property { 'none' | 'fixed' | 'percent' | 'combined' } surchargeType
     * @property {number?} fixedSurcharge
     * @property {number?} percentSurcharge
     * @property {number?} surchargeLimit
     * @property {string?} logo
     * @property {Blob?} logoFile
     * @property {'creditOrDebitCard' | 'buyNowPayLater' | 'cashOrAtm' | 'directDebit' | 'onlinePayments' | 'wallet' |
     *     'prepaidAndGiftCard' | 'mobile'} paymentType
     * @property {AdditionalDataConfig?} additionalData
     */
    /**
     * Handles payments pages logic.
     *
     * @param {{getConfiguredPaymentsUrl: string, getAvailablePaymentsUrl: string, addMethodConfigurationUrl: string,
     *     saveMethodConfigurationUrl: string, getMethodConfigurationUrl: string, deleteMethodConfigurationUrl: string
     *     }} configuration
     * @constructor
     */
    function PaymentsController(configuration) {
        /** @type AjaxServiceType */
        const api = AdyenFE.ajaxService;

        const {
            templateService,
            translationService,
            elementGenerator: generator,
            validationService: validator,
            components,
            utilities
        } = AdyenFE;

        const dataTableComponent = AdyenFE.components.DataTable;

        /** @type {HTMLElement} */
        let page;

        /** @type {Record<string, string[]>} */
        let activeFilters = {};

        /** @type {PaymentMethod[]} */
        let activeMethods = [];

        /** @type {PaymentMethod[]} */
        let availableMethods = [];

        /** @type {PaymentMethodConfiguration | null} */
        let activeMethod = null;

        /** @type {PaymentMethodConfiguration | null} */
        let changedMethod = null;

        /** @type {number} */
        let numberOfChanges = 0;

        /**
         * Replaces an active page with the other one rendered by a provider renderer method.
         *
         * @param {() => void} renderer
         */
        const switchPage = (renderer) => {
            utilities.showLoader();
            document.querySelector('.adl-form-footer')?.remove();
            if (!page) {
                page = generator.createElement('div', 'adl-payments-page');
            } else {
                templateService.clearComponent(page);
            }

            activeFilters = {};
            renderer();
        };

        /**
         * Creates payment methods table.
         *
         * @param {PaymentMethod[]} paymentMethods
         * @param {(cell: HTMLElement, method: PaymentMethod) => void} actionsCellRenderer
         * @return {HTMLElement}
         */
        const createMethodsTable = (paymentMethods, actionsCellRenderer) => {
            /**
             * @param {string[]} items
             */
            const createHintCell = (items) => {
                return (cell) => {
                    return cell.append(
                        generator.createHint(
                            `payments.list.multiItemLabel|${items[0]}|${items.length - 1}`,
                            items.join(', '),
                            'bottom'
                        )
                    );
                };
            };

            /** @type {TableCell[]} */
            const header = [
                {
                    label: 'payments.list.paymentMethod',
                    className: 'adlm--left-aligned'
                },
                {
                    label: 'payments.list.currencies'
                },
                {
                    label: 'payments.list.regions'
                },
                {
                    label: 'payments.list.type'
                },
                {
                    renderer: (cell) =>
                        cell.append(
                            generator.createElement('div', 'adlp-status-header', 'payments.list.status', null, [
                                generator.createHint('', 'payments.list.statusHint', 'top')
                            ])
                        )
                },
                {
                    label: 'payments.list.actions'
                }
            ];

            /** @type {TableCell[][]} */
            const rows = paymentMethods.map((method) => {
                return [
                    {
                        label: method.name,
                        className: 'adlm--left-aligned',
                        renderer: (cell) =>
                            cell.prepend(generator.createElement('img', 'adlp-payment-logo', '', { src: method.logo }))
                    },
                    {
                        label: method.currencies?.length <= 2 ? method.currencies?.join(', ') : '',
                        renderer: method.currencies?.length > 2 ? createHintCell(method.currencies) : null
                    },
                    {
                        label: method.countries?.length <= 2 ? method.countries?.join(', ') : '',
                        renderer: method.countries?.length > 2 ? createHintCell(method.countries) : null
                    },
                    {
                        label: `payments.paymentTypes.${method.paymentType}`
                    },
                    {
                        renderer: (cell) =>
                            cell.append(
                                generator.createElement(
                                    'span',
                                    'adlp-status adlt--' + (method.status ? 'active' : 'inactive'),
                                    `payments.list.status${method.status ? 'Active' : 'Inactive'}`
                                )
                            )
                    },
                    {
                        renderer: (cell) => actionsCellRenderer(cell, method)
                    }
                ];
            });

            return dataTableComponent.createPaymentsDataTable(header, rows);
        };

        /**
         * Renders or replaces the methods table.
         *
         * @param {HTMLElement} table
         */
        const renderMethodsTable = (table) => {
            page.querySelector('.adlp-no-items-wrapper')?.remove();
            const existingTable = page.querySelector('.adl-table-wrapper');
            const backButton = page.querySelector('.adlp-back-button');
            if (existingTable) {
                existingTable.parentElement?.remove();
            }

            if (backButton) {
                page.insertBefore(table, backButton);
            } else {
                page.append(table);
            }
        };

        /**
         * Filters methods based on the current filter.
         *
         * @param {PaymentMethod[]} methods
         * @returns {PaymentMethod[]}
         */
        const applyFilter = (methods) => {
            return methods.filter((method) => {
                if (activeFilters.types?.length && !activeFilters.types.includes(method.paymentType)) {
                    return false;
                }

                if (
                    activeFilters.status?.length &&
                    !activeFilters.status.includes(method.status ? 'active' : 'inactive')
                ) {
                    return false;
                }

                if (activeFilters.currencies?.length) {
                    return method.currencies.reduce(
                        (result, code) => result || activeFilters.currencies.includes(code) || code === 'ANY',
                        false
                    );
                }

                if (activeFilters.countries?.length) {
                    return method.countries.reduce(
                        (result, code) => result || activeFilters.countries.includes(code) || code === 'ANY',
                        false
                    );
                }

                return true;
            });
        };

        /**
         * Renders the active payments form.
         */
        const renderActivePaymentsForm = () => {
            page.append(
                generator.createElement('div', 'adl-payment-methods-header', '', null, [
                    generator.createElement('div', '', '', null, [
                        generator.createElement('h2', 'adlp-main-title', 'payments.active.title',
                            {dataset: {heading:  "active-payment-methods"}}),
                        generator.createElement('p', '', 'payments.active.description')
                    ]),
                    generator.createButton({
                        type: 'primary',
                        name: 'addMethodsButton',
                        className: 'adlp-add-methods-button',
                        label: 'payments.active.addMethod',
                        onClick: () => switchPage(renderChooseMethodPage)
                    })
                ])
            );

            api.get(configuration.getConfiguredPaymentsUrl)
                .then((methods) => {
                    activeMethods = methods;
                    if (!methods?.length) {
                        page.append(dataTableComponent.createNoItemsMessage('payments.active.noMethodsMessage'));
                        templateService.getMainPage().append(page);
                    } else {
                        return api.get(configuration.getAvailablePaymentsUrl).then((allMethods) => {
                            allMethods.forEach((m) => (m.methodName = m.name));
                            availableMethods = allMethods;

                            page.append(renderPaymentsTableFilter(renderActiveMethodsTable));
                            renderActiveMethodsTable();

                            templateService.getMainPage().append(page);
                        });
                    }
                })
                .catch(() => false)
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Renders table for the active payment methods.
         */
        const renderActiveMethodsTable = () => {
            const data = applyFilter(
                activeMethods.map((method) => ({
                    ...availableMethods.find((m) => m.methodId === method.methodId),
                    ...method
                }))
            );
            if (data.length) {
                renderMethodsTable(
                    createMethodsTable(data, (cell, method) => {
                        cell.append(
                            generator.createButton({
                                type: 'ghost',
                                size: 'small',
                                className: 'adlt--edit-button',
                                label: 'general.edit',
                                onClick: () => switchPage(() => renderPaymentConfigForm(method))
                            }),
                            generator.createButton({
                                type: 'ghost',
                                size: 'small',
                                className: 'adlt--delete-button adlm--destructive',
                                label: 'general.delete',
                                onClick: () => renderDeleteModal(method)
                            })
                        );
                    })
                );
            } else {
                renderMethodsTable(dataTableComponent.createNoItemsMessage('payments.list.noMethodsForFilter'));
            }
        };

        /**
         * Handles choosing method page.
         */
        const renderChooseMethodPage = () => {
            page.append(
                generator.createElement('h2', '', 'payments.add.title'),
                generator.createElement('p', '', 'payments.add.description'),
                renderPaymentsTableFilter(renderAvailableMethodsTable)
            );

            api.get(configuration.getAvailablePaymentsUrl)
                .then((methods) => {
                    return api.get(configuration.getConfiguredPaymentsUrl).then((configuredPayments) => {
                        // filter out already configured methods.
                        methods = (methods || []).filter(
                            (a) => !(configuredPayments || []).find((c) => c.methodId === a.methodId)
                        );

                        availableMethods = methods;

                        if (!methods?.length) {
                            page.append(dataTableComponent.createNoItemsMessage('payments.add.noMethodsMessage'));
                        } else {
                            renderAvailableMethodsTable();
                        }

                        templateService.getMainPage().append(page);
                    });
                })
                .finally(() => {
                    page.append(
                        generator.createButton({
                            type: 'secondary',
                            label: 'payments.add.back',
                            className: 'adlp-back-button',
                            onClick: () => switchPage(renderActivePaymentsForm)
                        })
                    );
                    utilities.hideLoader();
                });
        };

        /**
         * Renders the list of available payment methods
         */
        const renderAvailableMethodsTable = () => {
            const data = applyFilter(availableMethods);
            if (data.length) {
                renderMethodsTable(
                    createMethodsTable(data, (cell, method) => {
                        cell.append(
                            generator.createButton({
                                type: 'secondary',
                                size: 'small',
                                className: 'adlt--add-button adlm--blue adlm--no-wrap',
                                label: 'payments.list.actionsAdd',
                                onClick: () => switchPage(() => renderPaymentConfigForm(method)),
                                dataset: { code: method.code }
                            })
                        );
                    })
                );
            } else {
                renderMethodsTable(dataTableComponent.createNoItemsMessage('payments.list.noMethodsForFilter'));
            }
        };

        /**
         * Creates payments table filer.
         */
        const renderPaymentsTableFilter = (renderer) => {
            let container = page.querySelector('.adlp-table-filter-wrapper');
            let filters;
            if (!container) {
                filters = generator.createElement('div', 'adlp-table-filters');
                container = generator.createElement('div', 'adlp-table-filter-wrapper', '', null, [
                    generator.createButton({
                        type: 'ghost',
                        size: 'medium',
                        className: 'adlm--blue adlp-filters-switch-button',
                        label: 'payments.list.filter',
                        onClick: () => {
                            container.classList.toggle('adls--filters-active');
                        }
                    }),
                    filters
                ]);
            } else {
                filters = container.querySelector('.adlp-table-filters');
                filters.innerHTML = '';
            }

            const changeFilter = (filter, values) => {
                activeFilters[filter] = values;
                resetButton.disabled =
                    Object.values(activeFilters).reduce((result, options) => result + options.length, 0) === 0;
                renderer();
            };

            const resetButton = generator.createButton({
                type: 'ghost',
                label: 'payments.filter.resetAll',
                size: 'small',
                className: 'adlp-reset-button',
                disabled: true,
                onClick: () => {
                    activeFilters = {};
                    renderPaymentsTableFilter(renderer);
                    renderer();
                    resetButton.disabled = true;
                }
            });

            filters.append(
                ...[
                    components.TableFilter.create({
                        name: 'types',
                        isMultiselect: true,
                        label: translationService.translate('payments.filter.types.label'),
                        labelPlural: translationService.translate('payments.filter.types.labelPlural'),
                        values: activeFilters.types || [],
                        options: methodTypes.map((key) => ({
                            value: key,
                            label: translationService.translate(`payments.paymentTypes.${key}`)
                        })),
                        selectPlaceholder: 'payments.filter.types.selectPlaceholder',
                        onChange: (values) => changeFilter('types', values)
                    }),
                    components.TableFilter.create({
                        name: 'status',
                        isMultiselect: false,
                        label: translationService.translate('payments.filter.statuses.label'),
                        labelPlural: translationService.translate('payments.filter.statuses.labelPlural'),
                        values: activeFilters.status || [],
                        options: [
                            {
                                value: 'active',
                                label: translationService.translate(`payments.list.statusActive`)
                            },
                            {
                                value: 'inactive',
                                label: translationService.translate(`payments.list.statusInactive`)
                            }
                        ],
                        selectPlaceholder: 'payments.filter.statuses.selectPlaceholder',
                        onChange: (values) => changeFilter('status', values)
                    }),
                    components.TableFilter.create({
                        name: 'currencies',
                        isMultiselect: true,
                        label: translationService.translate('payments.filter.currencies.label'),
                        labelPlural: translationService.translate('payments.filter.currencies.labelPlural'),
                        values: activeFilters.currencies || [],
                        options: currencies.map((c) => ({ value: c, label: c })),
                        selectPlaceholder: 'payments.filter.currencies.selectPlaceholder',
                        onChange: (values) => changeFilter('currencies', values)
                    }),
                    components.TableFilter.create({
                        name: 'countries',
                        isMultiselect: true,
                        label: translationService.translate('payments.filter.countries.label'),
                        labelPlural: translationService.translate('payments.filter.countries.labelPlural'),
                        values: activeFilters.countries || [],
                        options: countries.map((c) => ({
                            value: c,
                            label: translationService.translate(`countries.${c}`)
                        })),
                        selectPlaceholder: 'payments.filter.countries.selectPlaceholder',
                        onChange: (values) => changeFilter('countries', values)
                    }),
                    resetButton
                ]
            );

            return container;
        };

        /**
         * Gets the default payment method configuration.
         *
         * @param {PaymentMethod} method
         * @returns {PaymentMethodConfiguration}
         */
        const getDefaultConfig = (method) => {
            const config = {
                isNew: true,
                methodId: method.methodId,
                code: method.code,
                paymentType: method.paymentType,
                logo: method.logo,
                name: method.methodName || method.name,
                description: 'Adyen ' + (method.methodName || method.name),
                surchargeType: 'none',
                additionalData: {}
            };

            if (method.paymentType === 'creditOrDebitCard') {
                config.additionalData = {
                    showLogos: true,
                    singleClickPayment: true,
                    sendBasket: true,
                    installments: false,
                    installmentAmounts: false,
                    numberOfInstallments: '',
                    installmentCountries: ['ANY'],
                    minimumAmount: null
                };
            }

            switch (method.code) {
                case 'molpay_ebanking_TH':
                    config.additionalData = {
                        showLogos: true,
                        bankIssuer: ''
                    };
                    break;
                case 'ideal':
                    config.additionalData = {
                        showLogos: true,
                        bankIssuer: ''
                    };
                    break;
                case 'eps':
                    config.additionalData = {
                        bankIssuer: ''
                    };
                    break;
                case 'applepay':
                    config.additionalData = {
                        merchantId: '',
                        merchantName: '',
                        displayButtonOn: true
                    };
                    break;
                case 'amazonpay':
                    config.additionalData = {
                        publicKeyId: '',
                        merchantId: '',
                        storeId: '',
                        displayButtonOn: true
                    };
                    break;
                case 'googlepay':
                case 'paywithgoogle':
                    config.additionalData = {
                        merchantId: '',
                        gatewayMerchantId: '',
                        displayButtonOn: true
                    };
                    break;
                case 'paypal':
                    config.additionalData = {
                        displayButtonOn: true
                    };
                    break;
            }

            return config;
        };

        /**
         * Renders the config form for the given payment method.
         *
         * @param {PaymentMethod} method
         */
        const renderPaymentConfigForm = (method) => {
            page.append(
                generator.createElement('div', 'adlp-configure-method-header', '', null, [
                    generator.createElement(
                        'h2',
                        '',
                        'payments.configure.title|' + (method.methodName || method.name),
                        null,
                        [
                            generator.createElement(
                                'div',
                                'adlp-status-badge adlt--' + (method.status ? 'active' : 'inactive'),
                                'payments.list.status' + (method.status ? 'Active' : 'Inactive')
                            )
                        ]
                    ),
                    generator.createElement('img', 'adlp-payment-logo', '', { src: method.logo })
                ]),
                generator.createElement(
                    'p',
                    '',
                    'payments.configure.description|' + (methodMap[method.code] || method.code)
                ),
                generator.createElement('p', 'adlp-flash-message-wrapper')
            );

            templateService.getMainPage().append(page);

            api.get(configuration.getMethodConfigurationUrl.replace('{methodId}', method.methodId), (error) => {
                if (error.status === 404) {
                    return Promise.resolve(null);
                }

                throw error;
            })
                .then(
                    /** @param {PaymentMethodConfiguration} config */
                    (config) => {
                        activeMethod = utilities.cloneObject(config);
                        if (!config?.code) {
                            config = getDefaultConfig(method);
                            activeMethod = utilities.cloneObject(config);

                            numberOfChanges = 2;
                        } else {
                            config.isNew = false;
                            config.paymentType = method.paymentType;

                            numberOfChanges = 0;
                        }

                        changedMethod = utilities.cloneObject(config);

                        renderCommonConfigForm();

                        switch (method.paymentType) {
                            case 'creditOrDebitCard':
                                renderCreditCardForm();
                                renderInstallmentsForm();
                        }

                        switch (method.code) {
                            case 'oney':
                                renderOneyForm();
                                break;
                            case 'molpay_ebanking_TH':
                            case 'ideal':
                                renderIssuersConfigurationForm();
                                break;
                            case 'eps':
                                renderEpsForm();
                                break;
                            case 'applepay':
                                renderApplePayForm();
                                break;
                            case 'amazonpay':
                                renderAmazonPayForm();
                                break;
                            case 'googlepay':
                            case 'paywithgoogle':
                                renderGooglePayForm();
                                break;
                            case 'paypal':
                                renderPayPalForm();
                                break;
                        }

                        page.append(generator.createFormFooter(handleSavePaymentMethod, navigateToPaymentsForm));
                        renderFooterState();
                        handleDependencies('installments', config.additionalData.installments, true);
                    }
                )
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Renders the common payment method config form.
         */
        const renderCommonConfigForm = () => {
            page.append(
                generator.createElement('div', 'adlp-separator'),
                ...generator.createFormFields(
                    [
                        {
                            name: 'name',
                            value: changedMethod.name,
                            type: 'text',
                            label: 'payments.configure.fields.name.label',
                            description: 'payments.configure.fields.name.description',
                            error: 'validation.requiredField'
                        },
                        {
                            name: 'description',
                            value: changedMethod.description,
                            type: 'text',
                            label: 'payments.configure.fields.description.label',
                            description: 'payments.configure.fields.description.description',
                            error: 'validation.requiredField'
                        },
                        {
                            name: 'surchargeType',
                            value: changedMethod.surchargeType,
                            type: 'dropdown',
                            label: 'payments.configure.fields.surchargeType.label',
                            description: 'payments.configure.fields.surchargeType.description',
                            placeholder: 'payments.configure.fields.surchargeType.placeholder',
                            options: [
                                { label: 'payments.configure.fields.surchargeType.none', value: 'none' },
                                { label: 'payments.configure.fields.surchargeType.fixed', value: 'fixed' },
                                { label: 'payments.configure.fields.surchargeType.percent', value: 'percent' },
                                { label: 'payments.configure.fields.surchargeType.combined', value: 'combined' }
                            ]
                        },
                        {
                            name: 'fixedSurcharge',
                            value: changedMethod.fixedSurcharge,
                            type: 'number',
                            dataset: {
                                validationRule: 'nonNegative'
                            },
                            label: 'payments.configure.fields.fixedSurcharge.label',
                            description: 'payments.configure.fields.fixedSurcharge.description',
                            error: 'validation.numeric'
                        },
                        {
                            name: 'percentSurcharge',
                            value: changedMethod.percentSurcharge,
                            type: 'number',
                            dataset: {
                                validationRule: 'nonNegative'
                            },
                            label: 'payments.configure.fields.percentSurcharge.label',
                            description: 'payments.configure.fields.percentSurcharge.description',
                            error: 'validation.numeric'
                        },
                        {
                            name: 'surchargeLimit',
                            value: changedMethod.surchargeLimit,
                            type: 'number',
                            dataset: {
                                validationRule: 'greaterThanZero,greaterThanX|fixedSurcharge'
                            },
                            label: 'payments.configure.fields.surchargeLimit.label',
                            description: 'payments.configure.fields.surchargeLimit.description',
                            error: 'payments.configure.fields.surchargeLimit.error'
                        },
                        {
                            name: 'logo',
                            value: changedMethod.logo,
                            type: 'file',
                            supportedMimeTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/svg'],
                            label: 'payments.configure.fields.logo.label',
                            placeholder: 'payments.configure.fields.logo.placeholder'
                        }
                    ].map((config) => ({
                        ...config,
                        onChange: (value) => handleConfigMethodChange(config.name, value)
                    }))
                )
            );

            handleDependencies('surchargeType', changedMethod.surchargeType);
        };

        /**
         * Renders the credit card config form.
         */
        const renderCreditCardForm = () => {
            page.append(
                ...generator.createFormFields([
                    getRadioField('creditCardFields', 'showLogos'),
                    getRadioField('creditCardFields', 'singleClickPayment'),
                    getRadioField('creditCardFields', 'sendBasket')
                ])
            );
        };

        /**
         * Renders the Apple Pay config form.
         */
        const renderApplePayForm = () => {
            page.append(
                ...generator.createFormFields([
                    getTextField('applePayFields', 'merchantId'),
                    getTextField('applePayFields', 'merchantName'),
                    getRadioField('applePayFields', 'displayButtonOn')
                ])
            );
        };

        /**
         * Renders the Amazon Pay config form.
         */
        const renderAmazonPayForm = () => {
            page.append(
                ...generator.createFormFields([
                    getTextField('amazonPayFields', 'publicKeyId', 'adl-amazon-pay-public-key-id'),
                    getTextField('amazonPayFields', 'merchantId', 'adl-amazon-pay-merchant-id'),
                    getTextField('amazonPayFields', 'storeId', 'adl-amazon-pay-store-id'),
                    getRadioField('amazonPayFields', 'displayButtonOn', 'adl-amazon-pay-display-button')
                ])
            );
        };

        /**
         * Renders the Google Pay config form.
         */
        const renderGooglePayForm = () => {
            page.append(
                ...generator.createFormFields([
                    getTextField('googlePayFields', 'gatewayMerchantId'),
                    getTextField('googlePayFields', 'merchantId'),
                    getRadioField('googlePayFields', 'displayButtonOn')
                ])
            );
        };

        /**
         * Renders the PayPal config form.
         */
        const renderPayPalForm = () => {
            page.append(...generator.createFormFields([getRadioField('paypalFields', 'displayButtonOn')]));
        };

        /**
         * Renders the EPS config form.
         */
        const renderEpsForm = () => {
            page.append(...generator.createFormFields([getTextField('issuersFields', 'bankIssuer')]));
        };

        /**
         * Renders the Oney config form.
         */
        const renderOneyForm = () => {
            page.append(
                ...generator.createFormFields([
                    getMultiselectField(
                        'oneyFields',
                        'supportedInstallments',
                        [
                            { label: 'oneyValues.3x', value: '3' },
                            { label: 'oneyValues.4x', value: '4' },
                            { label: 'oneyValues.6x', value: '6' },
                            { label: 'oneyValues.10x', value: '10' },
                            { label: 'oneyValues.12x', value: '12' }
                        ],
                        'adlm--inline',
                        false,
                        'payments.configure.fields.oneyFields.supportedInstallments.placeholder'
                    )
                ])
            );
        };

        /**
         * Renders the installments config form.
         */
        const renderInstallmentsForm = () => {
            page.append(
                ...generator.createFormFields([
                    getRadioField('installmentFields', 'installments'),
                    getRadioField('installmentFields', 'installmentAmounts'),
                    getMultiselectField('installmentFields', 'installmentCountries', [
                        { label: 'countries.BR', value: 'BR' },
                        { label: 'countries.MX', value: 'MX' },
                        { label: 'countries.TK', value: 'TK' },
                        { label: 'countries.JP', value: 'JP' }
                    ]),
                    getNumberField('installmentFields', 'minimumAmount', '', false, 0.01, 0.01),
                    getTextField('installmentFields', 'numberOfInstallments')
                ])
            );
        };

        /**
         * Renders the issuers config form.
         */
        const renderIssuersConfigurationForm = () => {
            page.append(
                ...generator.createFormFields([
                    getRadioField('issuersFields', 'showLogos'),
                    getTextField('issuersFields', 'bankIssuer')
                ])
            );
        };

        /**
         * Gets the configuration for the radio input field.
         *
         * @param {string} type
         * @param {string} name
         * @param {string} className
         * @returns {FormField}
         */
        const getRadioField = (type, name, className = '') => {
            return {
                name,
                value: changedMethod.additionalData?.[name] ? '1' : '0',
                type: 'radio',
                className,
                label: `payments.configure.fields.${type}.${name}.label`,
                description: `payments.configure.fields.${type}.${name}.description`,
                options: [
                    { label: 'general.yes', value: '1' },
                    { label: 'general.no', value: '0' }
                ],
                onChange: (value) => handleConfigMethodChange(name, value === '1', true)
            };
        };

        /**
         * Gets the configuration for the text input field.
         *
         * @param {string} type
         * @param {string} name
         * @param {string} className
         * @returns {FormField}
         */
        const getTextField = (type, name, className = '') => {
            return {
                name,
                value: changedMethod.additionalData?.[name] || '',
                type: 'text',
                className,
                label: `payments.configure.fields.${type}.${name}.label`,
                description: `payments.configure.fields.${type}.${name}.description`,
                error: 'validation.requiredField',
                onChange: (value) => handleConfigMethodChange(name, value, true)
            };
        };

        /**
         * Gets the configuration for the number input field.
         *
         * @param {string} type
         * @param {string} name
         * @param {string} className
         * @param {boolean?} isInt
         * @param {number?} step
         * @param {number?} min
         * @returns {FormField}
         */
        const getNumberField = (type, name, className = '', isInt = true, step = 1, min = 1) => {
            return {
                name,
                value: changedMethod.additionalData?.[name] || '',
                type: 'number',
                className,
                step: step,
                min: min,
                dataset: {
                    validationRule: (isInt ? 'integer,' : '') + 'greaterThanZero'
                },
                label: `payments.configure.fields.${type}.${name}.label`,
                description: `payments.configure.fields.${type}.${name}.description`,
                error: 'validation.greaterThanZero',
                onChange: (value) => handleConfigMethodChange(name, value, true)
            };
        };

        /**
         * Gets the configuration for the multiselect field.
         *
         * @param {string} type
         * @param {string} name
         * @param {Option[]} options
         * @param {string} className
         * @param {boolean} useAny
         * @param {string} placeholder
         * @returns {FormField}
         */
        const getMultiselectField = (
            type,
            name,
            options,
            className = '',
            useAny = true,
            placeholder = 'general.any'
        ) => {
            return {
                name,
                values: changedMethod.additionalData?.[name] || [],
                type: 'multiselect',
                className,
                label: `payments.configure.fields.${type}.${name}.label`,
                description: `payments.configure.fields.${type}.${name}.description`,
                selectedText: 'general.selectedItems',
                placeholder,
                options,
                useAny,
                error: 'validation.requiredField',
                onChange: (value) => handleConfigMethodChange(name, value, true)
            };
        };

        /**
         * Renders the modal for confirming the deletion of the payment method configuration.
         *
         * @param {PaymentMethod} method
         */
        const renderDeleteModal = (method) => {
            const modal = components.Modal.create({
                title: 'payments.delete.title',
                content: [generator.createElement('span', '', 'payments.delete.description')],
                footer: true,
                canClose: true,
                buttons: [
                    { type: 'secondary', label: 'general.cancel', onClick: () => modal.close() },
                    {
                        type: 'primary',
                        className: 'adlm--destructive',
                        label: 'general.delete',
                        onClick: () => {
                            deleteMethod(method).finally(() => {
                                modal.close();
                            });
                        }
                    }
                ]
            });

            modal.open();
        };

        /**
         * Handles form input field change.
         *
         * @param {string} prop Changed property name.
         * @param {any} value New value.
         * @param {boolean?} additional Indicates whether the changed property belongs to the additional config fields.
         */
        const handleConfigMethodChange = (prop, value, additional) => {
            const areDifferent = (source, target) => {
                if (Array.isArray(source) && Array.isArray(target)) {
                    return !AdyenFE.utilities.compareArrays(source, target);
                }

                return source !== target;
            };

            numberOfChanges = 0;
            if (additional) {
                if (!changedMethod.additionalData) {
                    changedMethod.additionalData = {};
                }

                changedMethod.additionalData[prop] = value;
            } else if (prop === 'logo') {
                changedMethod.logoFile = value;
                numberOfChanges = 1;
            } else {
                changedMethod[prop] = value;
            }

            Object.entries(changedMethod).forEach(([prop, value]) => {
                if (prop === 'additionalData') {
                    Object.entries(changedMethod.additionalData).forEach(([prop, value]) => {
                        areDifferent(activeMethod.additionalData[prop], value) && numberOfChanges++;
                    });
                } else if (!['logoFile', 'isNew', 'paymentType'].includes(prop)) {
                    areDifferent(activeMethod[prop], value) && numberOfChanges++;
                }
            });

            renderFooterState();
            handleDependencies(prop, value, additional);

            if (
                [
                    'name',
                    'description',
                    'showLogos',
                    'singleClickPayment',
                    'merchantName',
                    'sendBasket',
                    'gatewayMerchantId',
                    'merchantId',
                    'publicKeyId',
                    'storeId',
                    'installmentAmounts',
                    'supportedInstallments'
                ].includes(prop)
            ) {
                validateRequiredField([prop]);
            }

            if (prop === 'surchargeType') {
                ['fixedSurcharge', 'percentSurcharge', 'surchargeLimit'].forEach((prop) => {
                    validator.removeError(page.querySelector(`[name="${prop}"]`));
                });
            }

            const field = page.querySelector(`[name="${prop}"]`);

            if (['fixedSurcharge', 'percentSurcharge'].includes(prop)) {
                validator.validateNumber(field);
            }

            if (prop === 'surchargeLimit') {
                if (changedMethod.surchargeType === 'combined') {
                    field.dataset.validationRule = 'greaterThanZero,greaterThanX|fixedSurcharge';
                } else {
                    field.dataset.validationRule = 'greaterThanZero';
                }

                validator.validateNumber(field);
            }

            if (prop === 'numberOfInstallments') {
                validator.validateNumberList(page.querySelector('[name="numberOfInstallments"]'), true, false);
            }
        };

        /**
         * Handles dependencies change.
         *
         * @param {string} prop
         * @param {string | boolean} value
         * @param {boolean} additional
         */
        const handleDependencies = (prop, value, additional = false) => {
            if (prop === 'surchargeType') {
                handleFieldVisibility('fixedSurcharge', value === 'fixed' || value === 'combined');
                handleFieldVisibility('percentSurcharge', value === 'percent' || value === 'combined');
                handleFieldVisibility('surchargeLimit', value === 'percent' || value === 'combined');
            }

            if (additional && prop === 'installments') {
                handleFieldVisibility('installmentAmounts', value);
                handleFieldVisibility('installmentCountries', value);
                handleFieldVisibility('minimumAmount', value);
                handleFieldVisibility('numberOfInstallments', value);
            }
        };

        /**
         *
         * @param {string} fieldName
         * @param {boolean} condition
         */
        const handleFieldVisibility = (fieldName, condition) => {
            const field = utilities.getAncestor(page.querySelector(`[name="${fieldName}"]`), 'adl-field-wrapper');
            condition ? utilities.showElement(field) : utilities.hideElement(field);
        };

        /**
         * Handles save payment method.
         */
        const handleSavePaymentMethod = () => {
            utilities.showLoader();

            // Cannot use utilities.cloneObject here because it will delete uploaded file.
            const data = {
                ...changedMethod,
                additionalData: utilities.cloneObject(changedMethod.additionalData)
            };
            const create = data.isNew;
            delete data.isNew;
            delete data.paymentType;

            if (!isValid()) {
                utilities.hideLoader();
                return;
            }

            if (data.surchargeType === 'fixed') {
                data.percentSurcharge = '';
            } else if (data.surchargeType === 'percent') {
                data.fixedSurcharge = '';
            } else if (data.surchargeType === 'none') {
                data.percentSurcharge = '';
                data.fixedSurcharge = '';
                data.surchargeLimit = '';
            }

            const postData = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (key !== 'logoFile' && key !== 'additionalData') {
                    postData.append(key, value);
                }
            });

            if (!data.additionalData?.installments) {
                delete data.additionalData.installmentAmounts;
                delete data.additionalData.installmentCountries;
                delete data.additionalData.numberOfInstallments;
                delete data.additionalData.minimumAmount;
            } else {
                if (!Object.hasOwn(data.additionalData, 'installmentAmounts')) {
                    data.additionalData.installmentAmounts = false;
                }

                if (
                    !Object.hasOwn(data.additionalData, 'installmentCountries') ||
                    (Array.isArray(data.additionalData?.installmentCountries) &&
                        data.additionalData.installmentCountries.length === 0)
                ) {
                    data.additionalData.installmentCountries = ['ANY'];
                }

                if (!Object.hasOwn(data.additionalData, 'minimumAmount')) {
                    data.additionalData.minimumAmount = null;
                }
            }

            postData.append('additionalData', JSON.stringify(data.additionalData || null));

            if (data.logoFile) {
                postData.set('logo', data.logoFile, data.logoFile.name);
            }

            const method = create ? 'post' : 'post';
            const url = create
                ? configuration.addMethodConfigurationUrl
                : configuration.saveMethodConfigurationUrl.replace('{methodId}', data.methodId);

            api[method](url, postData, {
                'Content-Type': 'multipart/form-data'
            })
                .then(() => {
                    utilities.createToasterMessage('payments.configure.method' + (create ? 'Added' : 'Saved'));
                    navigateToPaymentsForm();
                })
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Validates the configuration form.
         *
         * @returns {boolean}
         */
        const isValid = () => {
            const result = [validateRequiredField(['name', 'description']), !page.querySelector(':invalid')];

            if (['fixed', 'combined'].includes(changedMethod.surchargeType)) {
                changedMethod.fixedSurcharge &&
                    result.push(validator.validateNumber(page.querySelector('[name="fixedSurcharge"]')));
            }

            if (['percent', 'combined'].includes(changedMethod.surchargeType)) {
                changedMethod.percentSurcharge &&
                    result.push(validator.validateNumber(page.querySelector('[name="percentSurcharge"]')));
                if (changedMethod.surchargeLimit) {
                    const surchargeLimitField = page.querySelector('[name="surchargeLimit"]');
                    if (changedMethod.surchargeType === 'combined') {
                        surchargeLimitField.dataset.validationRule = 'greaterThanZero,greaterThanX|fixedSurcharge';
                    } else {
                        surchargeLimitField.dataset.validationRule = 'greaterThanZero';
                    }

                    result.push(validator.validateNumber(surchargeLimitField));
                }
            }

            if (changedMethod.additionalData?.installments) {
                if (changedMethod.additionalData.minimumAmount) {
                    result.push(validator.validateNumber(page.querySelector('[name="minimumAmount"]')));
                }

                changedMethod.paymentType === 'creditOrDebitCard' &&
                    result.push(
                        validator.validateNumberList(page.querySelector('[name="numberOfInstallments"]'), true, false)
                    );
            }

            if (changedMethod.paymentType === 'creditOrDebitCard') {
                result.push(
                    ...validateRequiredField(['showLogos', 'singleClickPayment', 'sendBasket', 'installmentAmounts'])
                );
            } else if (changedMethod.code === 'applepay') {
                result.push(...validateRequiredField(['merchantId', 'merchantName']));
            } else if (changedMethod.code === 'amazonpay') {
                result.push(...validateRequiredField(['publicKeyId', 'merchantId', 'storeId']));
            } else if (changedMethod.code === 'googlepay' || changedMethod.code === 'paywithgoogle') {
                result.push(...validateRequiredField(['gatewayMerchantId', 'merchantId']));
            } else if (changedMethod.code === 'oney') {
                result.push(...validateRequiredField(['supportedInstallments']));
            }

            return !result.includes(false);
        };

        /**
         * Validates the additional form fields.
         *
         * @param {(keyof AdditionalDataConfig | 'name' | 'description')[]} fieldNames
         * @returns {boolean[]}
         */
        const validateRequiredField = (fieldNames) => {
            return fieldNames.map((fieldName) =>
                validator.validateRequiredField(page.querySelector(`[name=${fieldName}]`))
            );
        };

        /**
         * Deletes the payment method configuration.
         *
         * @param {PaymentMethod} method
         */
        const deleteMethod = (method) => {
            utilities.showLoader();

            return api
                .delete(configuration.deleteMethodConfigurationUrl.replace('{methodId}', method.methodId))
                .then(() => {
                    switchPage(renderActivePaymentsForm);
                })
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Handles footer visibility state.
         */
        const renderFooterState = () => {
            utilities.renderFooterState(numberOfChanges, false);
        };

        /**
         * Handles switching to payments form.
         */
        const navigateToPaymentsForm = () => {
            activeMethod = null;
            changedMethod = null;
            numberOfChanges = 0;
            switchPage(renderActivePaymentsForm);
        };

        /**
         * Displays page content.
         *
         * @param {{ storeId: string }} config
         */
        this.display = ({ storeId }) => {
            configuration.getConfiguredPaymentsUrl = configuration.getConfiguredPaymentsUrl.replace(
                '{storeId}',
                storeId
            );
            configuration.getAvailablePaymentsUrl = configuration.getAvailablePaymentsUrl.replace('{storeId}', storeId);
            configuration.getMethodConfigurationUrl = configuration.getMethodConfigurationUrl.replace(
                '{storeId}',
                storeId
            );
            configuration.saveMethodConfigurationUrl = configuration.saveMethodConfigurationUrl.replace(
                '{storeId}',
                storeId
            );
            configuration.addMethodConfigurationUrl = configuration.addMethodConfigurationUrl.replace(
                '{storeId}',
                storeId
            );
            configuration.deleteMethodConfigurationUrl = configuration.deleteMethodConfigurationUrl.replace(
                '{storeId}',
                storeId
            );
            templateService.clearMainPage();
            switchPage(renderActivePaymentsForm);
        };

        /**
         * Sets the unsaved changes.
         *
         * @return {boolean}
         */
        this.hasUnsavedChanges = () => {
            if (numberOfChanges > 0) {
                return true;
            }
        };
    }

    AdyenFE.PaymentsController = PaymentsController;
})();
