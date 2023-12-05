if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * @typedef GeneralSettings
     * @property {boolean} basketItemSync
     * @property {'delayed' | 'immediate' | 'manual' | null} capture
     * @property {number | null} captureDelay
     * @property {string | null} shipmentStatus
     * @property {number} retentionPeriod
     * @property {boolean} enablePayByLink
     * @property {string | null} payByLinkTitle
     * @property {string | null} defaultLinkExpirationTime
     */

    /**
     * @typedef OrderStatusMappingSettings
     * @property {string | null} inProgress
     * @property {string | null} pending
     * @property {string | null} paid
     * @property {string | null} failed
     * @property {string | null} refunded
     * @property {string | null} cancelled
     * @property {string | null} partiallyRefunded
     * @property {string | null} new
     * @property {string | null} chargeBack
     */

    /**
     * @typedef AdyenGivingSettings
     * @property {boolean} enableAdyenGiving
     * @property {string} charityName
     * @property {string} charityDescription
     * @property {string} charityMerchantAccount
     * @property {string} donationAmount
     * @property {string} logo
     * @property {Blob?} logoFile
     * @property {string} backgroundImage
     * @property {Blob?} backgroundImageFile
     * @property {string} charityWebsite
     */

    /**
     * @typedef SystemInfoSettings
     * @property {boolean} debugMode
     * @property {{status: boolean, message: string}} webhookValidate
     * @property {{status: boolean, message: string}} infoValidate
     * @property {string} systemInformation
     */

    /**
     * Handles connection page logic.
     *
     * @param {{
     *  getShippingStatusesUrl: string,
     *  getSettingsUrl: string,
     *  saveSettingsUrl: string,
     *  getOrderMappingsUrl: string,
     *  saveOrderMappingsUrl: string,
     *  getGivingUrl: string,
     *  saveGivingUrl: string,
     *  getSystemInfoUrl: string,
     *  saveSystemInfoUrl: string,
     *  webhookValidationUrl: string,
     *  integrationValidationUrl: string,
     *  integrationValidationTaskCheckUrl: string,
     *  downloadWebhookReportUrl: string,
     *  downloadIntegrationReportUrl: string,
     *  downloadSystemInfoFileUrl: string,
     *  page: string}}  configuration
     * @constructor
     */
    function SettingsController(configuration) {
        /** @type AjaxServiceType */
        const api = AdyenFE.ajaxService;

        const translationService = AdyenFE.translationService;

        const { templateService, elementGenerator: generator, validationService: validator, utilities } = AdyenFE;
        /** @type string */
        let currentStoreId = '';
        /** @type HTMLElement | null */
        let form = null;

        /** @type GeneralSettings | AdyenGivingSettings | OrderStatusMappingSettings */
        let activeSettings;
        /** @type GeneralSettings | AdyenGivingSettings | OrderStatusMappingSettings */
        let changedSettings;
        /** @type number */
        let numberOfChanges = 0;

        /** @type SystemInfoSettings */
        let systemSettings;

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({ storeId }) => {
            currentStoreId = storeId;
            templateService.clearMainPage();
            [
                'getShippingStatusesUrl',
                'getSettingsUrl',
                'saveSettingsUrl',
                'getGivingUrl',
                'saveGivingUrl',
                'getOrderMappingsUrl',
                'saveOrderMappingsUrl',
                'webhookValidationUrl',
                'downloadWebhookReportUrl'
            ].forEach((prop) => {
                configuration[prop] = configuration[prop].replace('{storeId}', storeId);
            });

            return renderPage();
        };

        /**
         * Sets the unsaved changes.
         *
         * @return {boolean}
         */
        this.hasUnsavedChanges = () => false;

        const scrollToTop = () => {
            document.querySelector('#adl-page > main')?.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
        };

        const renderPage = () => {
            utilities.showLoader();
            numberOfChanges = 0;
            scrollToTop();
            let url;
            let renderer;
            switch (configuration.page) {
                case 'adyen_giving':
                    url = configuration.getGivingUrl;
                    renderer = renderAdyenGivingSettingsForm;
                    break;
                case 'order_status_mapping':
                    url = configuration.getOrderMappingsUrl;
                    renderer = renderOrderStatusMappingForm;
                    break;
                case 'system_info':
                    url = configuration.getSystemInfoUrl;
                    renderer = renderSystemInfoForm;
                    break;
                default:
                    url = configuration.getSettingsUrl;
                    renderer = renderGeneralSettingsForm;
                    break;
            }

            return api
                .get(url, () => null)
                .then(renderer)
                .catch(renderer)
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Renders the general settings form.
         *
         * @param {GeneralSettings} settings
         */
        const renderGeneralSettingsForm = (settings) => {
            activeSettings = utilities.cloneObject(settings);
            if (!settings || Object.keys(settings).length === 0) {
                /** @type GeneralSettings */
                settings = {
                    basketItemSync: false,
                    retentionPeriod: 60,
                    capture: null,
                    captureDelay: null,
                    shipmentStatus: null
                };

                numberOfChanges = 3;
            }

            changedSettings = utilities.cloneObject(settings);

            return api
                .get(configuration.getShippingStatusesUrl, () => [])
                .then(
                    /** @param {{statusName: string, statusId: string}[]} shippingStatuses */
                    (shippingStatuses) => {
                        renderPageForm(
                            'adlp-general-settings',
                            'general',
                            true,
                            generator.createFormFields(
                                [
                                    {
                                        name: 'basketItemSync',
                                        value: settings.basketItemSync,
                                        type: 'checkbox'
                                    },
                                    {
                                        name: 'capture',
                                        value: settings.capture,
                                        type: 'dropdown',
                                        placeholder: 'settings.general.fields.capture.placeholder',
                                        error: 'settings.general.fields.capture.error',
                                        options: [
                                            { label: 'settings.general.fields.capture.delayed', value: 'delayed' },
                                            { label: 'settings.general.fields.capture.immediate', value: 'immediate' },
                                            { label: 'settings.general.fields.capture.manual', value: 'manual' }
                                        ]
                                    },
                                    {
                                        name: 'captureDelay',
                                        value: settings.captureDelay,
                                        type: 'number',
                                        className:
                                            'adlt--capture-delay' +
                                            (settings.capture !== 'delayed' ? ' adls--hidden ' : ''),
                                        step: 1,
                                        dataset: {
                                            validationRule: 'required,integer,minValue|1,maxValue|7'
                                        },
                                        error: 'settings.general.fields.captureDelay.error'
                                    },
                                    {
                                        name: 'shipmentStatus',
                                        value: settings.shipmentStatus,
                                        type: 'dropdown',
                                        placeholder: 'settings.general.fields.shipmentStatus.placeholder',
                                        options: shippingStatuses.map((status) => ({
                                            label: status.statusName,
                                            value: status.statusId
                                        })),
                                        className:
                                            'adlt--shipment-status' +
                                            (settings.capture !== 'manual' ? ' adls--hidden ' : '')
                                    },
                                    {
                                        name: 'retentionPeriod',
                                        value: settings.retentionPeriod,
                                        type: 'number',
                                        step: 1,
                                        min: 0,
                                        dataset: {
                                            validationRule: 'required,integer,minValue|60'
                                        },
                                        error: 'settings.general.fields.retentionPeriod.error'
                                    },
                                    {
                                        name: 'enablePayByLink',
                                        value: settings.enablePayByLink,
                                        type: 'checkbox',
                                        onChange: (value) => handleChange('enablePayByLink', value)
                                    },
                                    {
                                        name: 'payByLinkTitle',
                                        value: settings.payByLinkTitle,
                                        type: 'text',
                                        dataset: {
                                            validationRule: 'required,integer,minValue|60'
                                        },
                                        placeholder: translationService.translate(
                                            `settings.general.fields.payByLinkTitle.placeholder`
                                        ),
                                        className: !settings.enablePayByLink ? 'adls--hidden' : '',
                                        error: translationService.translate(
                                            'settings.general.fields.payByLinkTitle.error'
                                        )
                                    },
                                    {
                                        name: 'defaultLinkExpirationTime',
                                        value: settings.defaultLinkExpirationTime,
                                        type: 'number',
                                        min: 1,
                                        step: 1,
                                        dataset: {
                                            validationRule: 'required,integer,minValue|1'
                                        },
                                        error: 'settings.general.fields.defaultLinkExpirationTime.error',
                                        className: !settings.enablePayByLink ? 'adls--hidden' : ''
                                    }
                                ].map(
                                    /** @param {FormField} config */
                                    (config) => ({
                                        ...config,
                                        label: `settings.general.fields.${config.name}.label`,
                                        description: `settings.general.fields.${config.name}.description`,
                                        onChange: (value) => handleChange(config.name, value)
                                    })
                                )
                            )
                        );
                    }
                );
        };

        /**
         * Renders the order status mappings form.
         *
         * @param {OrderStatusMappingSettings} mappings
         */
        const renderOrderStatusMappingForm = (mappings) => {
            if (!mappings || Object.keys(mappings).length === 0) {
                /** @type {OrderStatusMappingSettings} */
                mappings = {
                    inProgress: null,
                    pending: null,
                    paid: null,
                    failed: null,
                    refunded: null,
                    partiallyRefunded: null,
                    cancelled: null,
                    new: null,
                    chargeBack: null
                };
            }

            activeSettings = utilities.cloneObject(mappings);
            changedSettings = utilities.cloneObject(mappings);

            return api
                .get(configuration.getShippingStatusesUrl, () => [])
                .then(
                    /**
                     * @param {{statusName: string, statusId: string}[]} orderStatuses
                     **/
                    (orderStatuses) => {
                        orderStatuses = [
                            { statusId: null, statusName: 'settings.orderStatusMapping.none' },
                            ...orderStatuses
                        ];
                        renderPageForm(
                            'adlp-order-status-mapping',
                            'orderStatusMapping',
                            true,
                            generator.createFormFields([
                                getDropdownField('inProgress', mappings, 'orderStatusMapping', orderStatuses),
                                getDropdownField('pending', mappings, 'orderStatusMapping', orderStatuses),
                                getDropdownField('paid', mappings, 'orderStatusMapping', orderStatuses),
                                getDropdownField('failed', mappings, 'orderStatusMapping', orderStatuses),
                                getDropdownField('refunded', mappings, 'orderStatusMapping', orderStatuses),
                                getDropdownField('partiallyRefunded', mappings, 'orderStatusMapping', orderStatuses),
                                getDropdownField(
                                    'cancelled',
                                    mappings,
                                    'orderStatusMapping',
                                    orderStatuses,
                                    'adlm--turned'
                                ),
                                getDropdownField('new', mappings, 'orderStatusMapping', orderStatuses, 'adlm--turned'),
                                getDropdownField(
                                    'chargeBack',
                                    mappings,
                                    'orderStatusMapping',
                                    orderStatuses,
                                    'adlm--turned'
                                )
                            ])
                        );
                    }
                );
        };

        /**
         * Gets the default settings for Adyen giving form.
         *
         * @returns {AdyenGivingSettings}
         */
        const getDefaultAdyenGivingSettings = () => ({
            enableAdyenGiving: false,
            charityName: '',
            logo: '',
            logoFile: null,
            backgroundImage: '',
            backgroundImageFile: null,
            charityDescription: '',
            charityMerchantAccount: '',
            charityWebsite: '',
            donationAmount: ''
        });

        /**
         * Renders the adyen giving settings form.
         *
         * @param {AdyenGivingSettings} adyenGiving
         */
        const renderAdyenGivingSettingsForm = (adyenGiving) => {
            activeSettings = utilities.cloneObject(adyenGiving);

            if (!adyenGiving || Object.keys(adyenGiving).length === 0) {
                /** @type AdyenGivingSettings */
                adyenGiving = getDefaultAdyenGivingSettings();

                numberOfChanges = 3;
            }

            changedSettings = utilities.cloneObject(adyenGiving);
            renderPageForm(
                'adlp-adyen-giving-settings',
                'adyenGiving',
                true,
                generator.createFormFields([
                    {
                        name: 'enableAdyenGiving',
                        value: changedSettings.enableAdyenGiving || false,
                        type: 'checkbox',
                        label: `settings.adyenGiving.fields.enableAdyenGiving.label`,
                        description: `settings.adyenGiving.fields.enableAdyenGiving.description`,
                        onChange: (value) => handleChange('enableAdyenGiving', value)
                    },
                    getTextField('charityName', 'adyenGiving'),
                    getTextField('charityDescription', 'adyenGiving'),
                    getTextField('charityMerchantAccount', 'adyenGiving'),
                    getTextField('donationAmount', 'adyenGiving'),
                    getTextField('charityWebsite', 'adyenGiving'),
                    getFileUploadField('backgroundImage', 'adyenGiving'),
                    getFileUploadField('logo', 'adyenGiving')
                ])
            );

            handleDependencies('enableAdyenGiving', adyenGiving.enableAdyenGiving);
        };

        /**
         * Renders settings form.
         *
         * @param {string} className
         * @param {string} page
         * @param {boolean?} useFooter
         * @param {HTMLElement[]} formFields
         */
        const renderPageForm = (className, page, useFooter, formFields) => {
            if (form) {
                templateService.clearComponent(form);
            }

            form = generator.createElement('div', className, '', null, [
                generator.createElement('div', 'adlp-flash-message-wrapper'),
                generator.createElement('h2', '', `settings.${page}.title`),
                generator.createElement('p', '', `settings.${page}.description`),
                ...formFields,
                useFooter
                    ? generator.createFormFooter(
                        handleSave,
                        () => {
                            if (numberOfChanges > 0) {
                                return renderPage();
                            } else {
                                scrollToTop();
                            }
                        },
                        'general.discardChanges'
                    )
                    : ''
            ]);

            templateService.getMainPage().append(form);
            if (useFooter) {
                renderFooterState(numberOfChanges);
            }
        };

        /**
         * Renders the system info settings form.
         *
         * @param {SystemInfoSettings} systemInfoSettings
         */
        const renderSystemInfoForm = (systemInfoSettings) => {
            systemSettings = { ...systemInfoSettings };

            renderPageForm(
                'adlp-system-info-settings',
                'system',
                false,
                generator.createFormFields([
                    {
                        name: 'debugMode',
                        value: systemInfoSettings.debugMode,
                        type: 'checkbox',
                        label: `settings.system.fields.debugMode.label`,
                        description: `settings.system.fields.debugMode.description`,
                        onChange: (value) => handleDebugMode(!!value)
                    },
                    getButtonField('adyenWebhooksValidation', 'adlp-setting-field', 'validate', handleValidateWebhooks),
                    getButtonField(
                        'integrationConfigurationValidation',
                        'adlp-setting-field',
                        'validate',
                        performIntegrationValidation
                    ),
                    getButtonLinkField(
                        'downloadSystemInformation',
                        'adlp-setting-field adlp-download-report',
                        'downloadReport',
                        configuration.downloadSystemInfoFileUrl
                    ),
                    getButtonLinkField(
                        'contactAdyenSupport',
                        'adlp-setting-field adlp-contact-adyen-support',
                        'contactAdyenSupport',
                        'https://www.adyen.help/hc/en-us'
                    )
                ])
            );
        };

        /**
         * Handles form input field change.
         *
         * @param {keyof (GeneralSettings & OrderStatusMappingSettings & AdyenGivingSettings & SystemInfoSettings)} prop
         * @param {any} value
         */
        const handleChange = (prop, value) => {
            if (['captureDelay', 'retentionPeriod'].includes(prop)) {
                changedSettings[prop] = Number(value);
            } else {
                changedSettings[prop] = value;
            }

            if (prop === 'capture') {
                handleOnCaptureChange(value);
            }

            if (prop === 'logo') {
                changedSettings.logoFile = value;
            }

            if (prop === 'backgroundImage') {
                changedSettings.backgroundImageFile = value;
            }

            validator.removeError(form.querySelector('[name="' + prop + '"]'));

            if (!configuration.page) {
                validateGeneralSettings(prop);
            } else if (configuration.page === 'adyen_giving') {
                validateAdyenGivingSettings(prop);
            }

            renderFooterState();
            handleDependencies(prop, value);
        };

        /**
         * Handles capture field change.
         *
         * @param {'delayed' | 'immediate' | 'manual'} value
         */
        const handleOnCaptureChange = (value) => {
            utilities.hideElement(form.querySelector('.adlt--capture-delay'));
            utilities.hideElement(form.querySelector('.adlt--shipment-status'));
            switch (value) {
                case 'delayed':
                    utilities.showElement(form.querySelector('.adlt--capture-delay'));
                    break;
                case 'manual':
                    utilities.showElement(form.querySelector('.adlt--shipment-status'));
                    break;
            }
        };

        /**
         * Handles dependencies change.
         *
         * @param {string} prop
         * @param {string | boolean} value
         */
        const handleDependencies = (prop, value) => {
            if (prop === 'enableAdyenGiving') {
                handleFieldVisibility('charityName', value);
                handleFieldVisibility('charityDescription', value);
                handleFieldVisibility('charityMerchantAccount', value);
                handleFieldVisibility('donationAmount', value);
                handleFieldVisibility('charityWebsite', value);
                handleFieldVisibility('backgroundImage', value);
                handleFieldVisibility('logo', value);
            }

            if (prop === 'enablePayByLink') {
                handleFieldVisibility('payByLinkTitle', value);
                handleFieldVisibility('defaultLinkExpirationTime', value);
            }
        };

        /**
         * Handles field visibility.
         *
         * @param {string} fieldName
         * @param {boolean} condition
         */
        const handleFieldVisibility = (fieldName, condition) => {
            const field = utilities.getAncestor(form.querySelector(`[name="${fieldName}"]`), 'adl-field-wrapper');
            condition ? utilities.showElement(field) : utilities.hideElement(field);
        };

        /**
         * Gets the configuration for the dropdown field.
         *
         * @param {string} name
         * @param {GeneralSettings | OrderStatusMappingSettings | AdyenGivingSettings} settingsTypeObject
         * @param {string} settingsTypeName
         * @param {string?} className
         * @param {{statusName: string, statusId: string}[]} options
         * @returns {FormField}
         */
        const getDropdownField = (name, settingsTypeObject, settingsTypeName, options, className = '') => {
            return {
                name,
                value: settingsTypeObject?.[name] || null,
                type: 'dropdown',
                className,
                placeholder: `settings.${settingsTypeName}.fields.${name}.placeholder`,
                label: `settings.${settingsTypeName}.fields.${name}.label`,
                description: `settings.${settingsTypeName}.fields.${name}.description`,
                options: options.map((status) => ({
                    label: status.statusName,
                    value: status.statusId
                })),
                onChange: (value) => handleChange(name, value)
            };
        };

        /**
         * Gets the configuration for the file upload field.
         *
         * @param {string} name
         * @param {string} settingsTypeName
         * @returns {FormField}
         */
        const getFileUploadField = (name, settingsTypeName) => {
            return {
                name,
                value: changedSettings?.[name] || '',
                type: 'file',
                supportedMimeTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/svg'],
                label: `settings.${settingsTypeName}.fields.${name}.label`,
                description: `settings.${settingsTypeName}.fields.${name}.description`,
                placeholder: `settings.${settingsTypeName}.fields.${name}.placeholder`,
                error: 'validation.requiredField',
                onChange: (value) => handleChange(name, value)
            };
        };

        /**
         * Gets the configuration for the text input field.
         *
         * @param {string} name
         * @param {string} settingsTypeName
         * @returns {FormField}
         */
        const getTextField = (name, settingsTypeName) => {
            return {
                name,
                value: changedSettings?.[name] || '',
                type: 'text',
                label: `settings.${settingsTypeName}.fields.${name}.label`,
                description: `settings.${settingsTypeName}.fields.${name}.description`,
                error: 'validation.requiredField',
                onChange: (value) => handleChange(name, value)
            };
        };

        /**
         * Gets the configuration for the button field.
         *
         * @param {string} name
         * @param {string?} className
         * @param {string} buttonText
         * @param {() => void?} onClick
         */
        const getButtonField = (name, className, buttonText, onClick) => {
            return {
                name: name,
                className: className,
                value: '',
                type: 'button',
                buttonType: 'secondary',
                buttonSize: 'medium',
                buttonLabel: `settings.system.buttons.` + buttonText,
                label: `settings.system.fields.${name}.label`,
                description: `settings.system.fields.${name}.description`,
                onClick: onClick
            };
        };

        /**
         * Gets the configuration for the link (that looks like a button) field.
         *
         * @param {string} name
         * @param {string?} className
         * @param {string} text
         * @param {string} href
         * @param {boolean?} useDownload
         * @param {string?} downloadFile
         */
        const getButtonLinkField = (name, className, text, href, useDownload, downloadFile) => {
            return {
                name: name,
                className: className,
                value: '',
                type: 'buttonLink',
                text: `settings.system.buttons.` + text,
                href: href,
                useDownload: useDownload,
                downloadFile: downloadFile,
                label: `settings.system.fields.${name}.label`,
                description: `settings.system.fields.${name}.description`
            };
        };

        /**
         * Handles footer visibility state.
         *
         * @param {number} changes
         */
        const renderFooterState = (changes = 0) => {
            numberOfChanges = changes;
            Object.entries(changedSettings).forEach(([prop, value]) => {
                if (!['logoFile', 'backgroundImageFile'].includes(prop) && activeSettings[prop] !== value) {
                    numberOfChanges++;
                }
            });

            if (changedSettings.logoFile) {
                numberOfChanges++;
            }

            if (changedSettings.backgroundImageFile) {
                numberOfChanges++;
            }

            utilities.renderFooterState(numberOfChanges);
        };

        /**
         * Handles debug mode change.
         *
         * @param {boolean} value
         */
        const handleDebugMode = (value) => {
            utilities.showLoader();

            let data = { debugMode: value };

            api.post(configuration.saveSystemInfoUrl, data)
                .then(renderPage)
                .then(() => {
                    utilities.createToasterMessage('settings.system.debugMode' + (value ? 'Enabled' : 'Disabled'));
                })
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        const performIntegrationValidation = () => {
            let url;

            const doCall = () => {
                api.get(url, (e) => {
                    throw e;
                })
                    .then((response) => {
                        if (response?.finished) {
                            showMessage(
                                [
                                    `settings.system.messages.${
                                        response?.status
                                            ? 'successIntegrationValidation'
                                            : 'failedIntegrationValidation'
                                    }`,
                                    'settings.system.messages.downloadReportText|' +
                                    configuration.downloadIntegrationReportUrl
                                ],
                                response?.status ? 'success' : 'error'
                            );

                            utilities.hideLoader();
                        } else {
                            setTimeout(doCall, 500);
                        }
                    })
                    .catch((reason) => {
                        showMessage(`general.errors.${reason?.errorCode || 'unknown'}`, 'error');
                        utilities.hideLoader();
                    });
            };

            utilities.showLoader();
            api.post(configuration.integrationValidationUrl).then(
                /** @param {{queueItemId: string}} response */
                (response) => {
                    url = configuration.integrationValidationTaskCheckUrl.replace(
                        '{queueItemId}',
                        response.queueItemId
                    );

                    setTimeout(doCall, 500);
                }
            );
        };

        /**
         * Handles webhooks and integration configuration validation.
         */
        const handleValidateWebhooks = () => {
            utilities.showLoader();

            api.post(configuration.webhookValidationUrl, null, null, (response) => response)
                .then((response) => {
                    showMessage(
                        [
                            'settings.system.messages.' +
                            (response?.status ? 'success' : 'failed') +
                            'WebhookValidation',
                            'settings.system.messages.downloadReportText|' + configuration.downloadWebhookReportUrl
                        ],
                        response?.status ? 'success' : 'error'
                    );
                })
                .catch(() => false)
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Saves the current page.
         */
        const handleSave = () => {
            utilities.showLoader();

            if (!configuration.page && !validateGeneralSettings()) {
                utilities.hideLoader();
                return;
            }

            if (configuration.page === 'adyen_giving' && !validateAdyenGivingSettings()) {
                utilities.hideLoader();
                return;
            }

            let data = { ...changedSettings };

            let promise;
            if (configuration.page === 'adyen_giving') {
                if (!data.enableAdyenGiving) {
                    data = getDefaultAdyenGivingSettings();
                }

                const postData = new FormData();
                Object.entries(data).forEach(([key, value]) => {
                    if (!['logo', 'logoFile', 'backgroundImage', 'backgroundImageFile'].includes(key)) {
                        postData.append(key, value);
                    }
                });

                if (data.logoFile) {
                    postData.set('logo', data.logoFile, data.logoFile.name);
                }

                if (data.backgroundImageFile) {
                    postData.set('backgroundImage', data.backgroundImageFile, data.backgroundImageFile.name);
                }

                promise = api.post(configuration.saveGivingUrl, postData, {
                    'Content-Type': 'multipart/form-data'
                });
            } else if (configuration.page === 'order_status_mapping') {
                promise = api.post(configuration.saveOrderMappingsUrl, data);
            } else {
                if (data.capture !== 'delayed') {
                    data.captureDelay = null;
                }
                if (!changedSettings.enablePayByLink) {
                    data.payByLinkTitle = '';
                    data.defaultLinkExpirationTime = '';
                }
                promise = api.post(configuration.saveSettingsUrl, data);
            }

            promise
                .then(renderPage)
                .then(showMessage)
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Validates the general settings form.
         *
         * @param {keyof GeneralSettings?} prop Indicates a field to be validated.
         * @returns {boolean} TRUE if all the fields are valid.
         */
        const validateGeneralSettings = (prop) => {
            const captureInput = form.querySelector('[name="capture"]');

            const result = [];
            if (!prop || prop === 'capture') {
                result.push(validator.validateRequiredField(captureInput));
            }

            if (!prop || prop === 'captureDelay') {
                result.push(
                    captureInput.value !== 'delayed' ||
                    validator.validateNumber(form.querySelector('[name="captureDelay"]'))
                );
            }

            if (!prop || prop === 'shipmentStatus') {
                result.push(
                    captureInput.value !== 'manual' ||
                    validator.validateRequiredField(form.querySelector('[name="shipmentStatus"]'))
                );
            }

            if (!prop || prop === 'retentionPeriod') {
                result.push(validator.validateNumber(form.querySelector('[name="retentionPeriod"]')));
            }

            if (changedSettings.enablePayByLink && (!prop || prop === 'payByLinkTitle')) {
                result.push(validator.validateRequiredField(form.querySelector('[name="payByLinkTitle"]')));
            }

            if (!prop) {
                result.push(!form.querySelector(':invalid'));
            }

            return !result.includes(false);
        };

        /**
         * Validates Adyen giving form.
         *
         * @param {keyof AdyenGivingSettings?} prop Indicates a field to be validated.
         * @returns {boolean} TRUE if all the fields are valid.
         */
        const validateAdyenGivingSettings = (prop) => {
            if (changedSettings.enableAdyenGiving) {
                const fields = prop
                    ? [prop]
                    : [
                        'charityName',
                        'logo',
                        'backgroundImage',
                        'charityDescription',
                        'charityMerchantAccount',
                        'charityWebsite',
                        'donationAmount'
                    ];

                const result = [];
                fields.forEach((fieldName) => {
                    const field = form.querySelector(`[name=${fieldName}]`);
                    if (['charityName', 'charityDescription', 'charityMerchantAccount'].includes(fieldName)) {
                        result.push(validator.validateRequiredField(field));
                    } else if (fieldName === 'charityWebsite') {
                        result.push(validateUrlField(fieldName, false));
                    } else if (['logo', 'backgroundImage'].includes(fieldName)) {
                        if (!changedSettings[fieldName] && !changedSettings[fieldName + 'File']) {
                            validator.setError(field);
                            result.push(false);
                        }
                    }

                    if (fieldName === 'donationAmount') {
                        result.push(validator.validateNumberList(field));
                    }
                });

                return !result.includes(false);
            }

            return true;
        };

        /**
         * Validates if the field is a valid URL. Additionally, validates if the field is set, if it is mandatory.
         * @param {string} name The name of the field.
         * @param {boolean} mandatory Indicates whether to validate a required field.
         * @return {boolean} TRUE if the field is a valid URL.
         */
        const validateUrlField = (name, mandatory) => {
            if (changedSettings[name]) {
                return validator.validateUrl(form.querySelector(`[name="${name}"]`), 'validation.invalidUrl');
            }

            return mandatory
                ? validator.validateRequiredField(form.querySelector(`[name="${name}"]`), 'validation.requiredField')
                : true;
        };

        /**
         * Displays the success flash message.
         *
         * @param {string|string[]} message
         * @param {'success'|'error'} type
         */
        const showMessage = (message = 'general.changesSaved', type = 'success') => {
            const container = form?.querySelector('.adlp-flash-message-wrapper');
            if (!container) {
                return;
            }

            templateService.clearComponent(container);
            container.append(utilities.createFlashMessage(message, type));
            scrollToTop();
        };
    }

    AdyenFE.SettingsController = SettingsController;
})();
