if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * @typedef ConnectionInfo
     * @property {string} apiKey
     * @property {string} merchantId
     */

    /**
     * @typedef Connection
     * @property {'test' | 'live'} mode
     * @property {ConnectionInfo?} testData
     * @property {ConnectionInfo?} liveData
     */

    /**
     * @typedef ConnectionSettings
     * @property {'test' | 'live'} mode
     * @property {string} apiKey
     * @property {string} merchantId
     * @property {{label: string, value: string}[]?} merchants
     */
    /**
     * Handles connection page logic.
     *
     * @param {{getSettingsUrl: string, submitUrl: string, disconnectUrl: string, getMerchantsUrl: string,
     *     validateConnectionUrl: string, validateWebhookUrl: string, page: string}} configuration
     * @constructor
     */
    function ConnectionController(configuration) {
        /** @type AjaxServiceType */
        const api = AdyenFE.ajaxService;

        const {
            templateService,
            elementGenerator: generator,
            validationService: validator,
            components,
            state,
            utilities
        } = AdyenFE;
        /** @type {HTMLElement} */
        let form;
        let currentStoreId;
        /** @type {boolean} */
        let merchantPage;
        /** @type {boolean} */
        let isConnectionSet;
        /** @type {ConnectionSettings} */
        let activeSettings;
        /** @type {ConnectionSettings} */
        let changedSettings;
        /** @type {number} */
        let numberOfChanges = 0;

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({ storeId }) => {
            utilities.showLoader();
            currentStoreId = storeId;
            merchantPage = configuration.page === 'merchant';
            templateService.clearMainPage();

            configuration.getSettingsUrl = configuration.getSettingsUrl.replace('{storeId}', storeId);
            configuration.getMerchantsUrl = configuration.getMerchantsUrl.replace('{storeId}', currentStoreId);
            configuration.submitUrl = configuration.submitUrl.replace('{storeId}', storeId);
            configuration.validateConnectionUrl = configuration.validateConnectionUrl.replace('{storeId}', storeId);
            configuration.validateWebhookUrl = configuration.validateWebhookUrl.replace('{storeId}', storeId);
            configuration.disconnectUrl = configuration.disconnectUrl.replace('{storeId}', storeId);

            state
                .getCurrentMerchantState()
                .then((state) => {
                    isConnectionSet = state === 'dashboard';
                    return api.get(configuration.getSettingsUrl, () => null).then(createForm);
                })
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Sets the unsaved changes.
         *
         * @return {boolean}
         */
        this.hasUnsavedChanges = () => false;

        /**
         * Renders the form.
         *
         * @param {ConnectionSettings} data
         */
        const renderForm = (data) => {
            form = generator.createElement('form');

            const components = [
                generator.createRadioGroupField({
                    name: 'mode',
                    value: data.mode || 'test',
                    label: 'connection.environment.title',
                    description: 'connection.environment.description',
                    options: [
                        { label: 'connection.environment.options.test', value: 'test' },
                        { label: 'connection.environment.options.live', value: 'live' }
                    ],
                    onChange: (value) => handleChange('mode', value)
                }),
                generator.createPasswordField({
                    name: 'apiKey',
                    value: data.apiKey,
                    label: 'connection.apiKey.title',
                    description: 'connection.apiKey.description',
                    error: 'connection.apiKey.error',
                    onChange: (value) => handleChange('apiKey', value)
                }),
                generator.createDropdownField({
                    name: 'merchantId',
                    value: data.merchantId,
                    placeholder: 'connection.merchant.placeholder',
                    options: data.merchants,
                    label: 'connection.merchant.title',
                    description: 'connection.merchant.description',
                    error: 'connection.merchant.error',
                    onChange: (value) => handleChange('merchantId', value)
                })
            ];

            if (merchantPage) {
                components[0].classList.add('adls--hidden');
                components[1].classList.add('adls--hidden');
            } else {
                components[2].classList.add('adls--hidden');
            }

            form.append(
                generator.createElement('div', 'adlp-flash-message-wrapper'),
                generator.createElement('h2', 'adlp-main-title', `connection.title${merchantPage ? '_merchant' : ''}`,
                    {dataset: {heading: `${merchantPage ? "merchant-account" : "setup"}`}}),
                generator.createElement(
                    'p',
                    'adlp-merchant-account-description',
                    `connection.subtitle${merchantPage ? '_merchant' : ''}`
                ),
                ...components
            );

            if (isConnectionSet) {
                if (!merchantPage) {
                    form.append(
                        generator.createButton({
                            type: 'secondary',
                            name: 'validateButton',
                            disabled: !data.apiKey,
                            label: 'connection.validateCredentials',
                            onClick: handleValidateCredentials
                        })
                    );
                }

                form.append(
                    generator.createFormFooter(
                        handleFormSubmit,
                        () => {
                            this.display({ storeId: currentStoreId });
                        },
                        'general.discardChanges',
                        !merchantPage
                            ? [
                                  generator.createButton({
                                      type: 'secondary',
                                      name: 'disconnectButton',
                                      label: 'connection.disconnect',
                                      className: 'adlm--destructive',
                                      onClick: showDisconnectModal
                                  })
                              ]
                            : []
                    )
                );
            } else {
                form.append(
                    generator.createButton({
                        type: 'primary',
                        name: 'saveButton',
                        disabled: !data.apiKey,
                        label: merchantPage ? 'connection.next' : 'connection.connect',
                        onClick: handleFormSubmit
                    })
                );
            }

            templateService.clearMainPage();
            templateService.getMainPage().append(form);
        };

        /**
         * Creates the form.
         *
         * @param {Connection?} settings
         */
        const createForm = (settings) => {
            const mode = settings?.mode || 'test';
            /** @type ConnectionSettings */
            const data = { mode: mode, apiKey: '', merchantId: '', merchants: [] };
            if (settings?.[`${mode}Data`]) {
                data.apiKey = settings[`${mode}Data`].apiKey;
                data.merchantId = settings[`${mode}Data`].merchantId;
            }

            changedSettings = utilities.cloneObject(data);
            activeSettings = utilities.cloneObject(data);

            if (data.apiKey) {
                document
                    .querySelector('.adl-sidebar [href="#connection-merchant"]')
                    .parentElement.classList.remove('adls--disabled');
            }

            if (merchantPage) {
                if (!data.apiKey) {
                    state.goToState('connection');
                    return Promise.resolve();
                }

                return api
                    .get(configuration.getMerchantsUrl, () => [])
                    .then(
                        /** @param {Merchant[]} response */
                        (response) => {
                            data.merchants = response.map((merchant) => ({
                                value: merchant.merchantId,
                                label: merchant.merchantName
                            }));

                            renderForm(data);
                        }
                    );
            } else {
                renderForm(data);

                return Promise.resolve();
            }
        };

        /**
         *
         * @param {keyof ConnectionSettings} prop
         * @param {any} value
         */
        const handleChange = (prop, value) => {
            changedSettings[prop] = value;
            if (prop === 'mode') {
                changedSettings.apiKey = '';
                form['apiKey'].value = '';
            } else {
                validator.validateRequiredField(form['apiKey'], 'connection.apiKey.error');
            }

            if (isConnectionSet) {
                renderFooterState();
            } else {
                form['saveButton'].disabled = !form['apiKey'].value;
            }
        };

        /**
         * Converts form data to the settings object.
         *
         * @return {Connection}
         */
        const getFormData = () => ({
            mode: changedSettings.mode,
            [changedSettings.mode + 'Data']: {
                apiKey: changedSettings.apiKey,
                merchantId: changedSettings.merchantId || null
            }
        });

        /**
         * Saves the connection configuration.
         *
         * @returns {boolean}
         */
        const handleFormSubmit = () => {
            const isValid =
                validator.validateRequiredField(form['mode']) &&
                validator.validateRequiredField(form['apiKey'], 'connection.apiKey.error') &&
                (!merchantPage || validator.validateRequiredField(form['merchantId']));

            if (isValid) {
                if (isConnectionSet && activeSettings.mode !== changedSettings.mode) {
                    performChangeEnvironmentSteps();

                    return false;
                }

                utilities.showLoader();
                api.post(configuration.submitUrl, getFormData())
                    .then(handleSaveSuccess)
                    .finally(() => {
                        utilities.hideLoader();
                    });
            }

            return false;
        };

        const performChangeEnvironmentSteps = () => {
            showConfirmModal('changeEnvironment').then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                utilities.showLoader();
                changedSettings.merchantId = '';

                return api
                    .post(configuration.validateConnectionUrl, getFormData())
                    .then((response) => {
                        if (!response.status) {
                            showFlashMessage(response.errorCode, 'error');
                            return false;
                        }

                        return true;
                    })
                    .then((next) => next && api.delete(configuration.disconnectUrl).then(() => true))
                    .then(
                        (next) =>
                            next &&
                            api.post(configuration.submitUrl, getFormData()).then(() => {
                                state.goToState('connection-merchant');
                            })
                    )
                    .finally(() => {
                        utilities.hideLoader();
                    });
            });
        };

        const handleSaveSuccess = () => {
            const finishSave = () => {
                activeSettings = { ...changedSettings };
                renderFooterState();
                showFlashMessage('connection.messages.connectionUpdated', 'success');
            };

            utilities.remove401Message();
            if (merchantPage) {
                if (!isConnectionSet) {
                    state.enableSidebar();
                    state.goToState('payments');
                    state.setHeader();
                } else {
                    finishSave();
                }
            } else if (!isConnectionSet) {
                state.goToState('connection-merchant');
            } else {
                finishSave();
            }
        };

        /**
         * Validates credentials.
         *
         * @return {Promise<string|null>}
         */
        const handleValidateCredentials = () => {
            utilities.showLoader();
            return Promise.all([
                api
                    .post(configuration.validateConnectionUrl, getFormData(), () => false)
                    .then((response) => response.status),
                api.post(configuration.validateWebhookUrl).then((response) => response.status)
            ])
                .then(([t1, t2]) => {
                    if (t1 && t2) {
                        showFlashMessage('connection.messages.validCredentials', 'success');
                    } else if (!t1) {
                        showFlashMessage('connection.errors.credentialsValidationError', 'error');
                    } else {
                        showFlashMessage('connection.errors.webhookValidationError', 'error');
                    }

                    return t1 && t2;
                })
                .catch(() => false)
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Shows the disconnect confirmation modal.
         */
        const showDisconnectModal = () => {
            showConfirmModal('disconnect').then((confirmed) => confirmed && handleDisconnect());
        };

        /**
         * Shows the confirmation modal dialog.
         *
         * @param {string} type
         * @returns {Promise}
         */
        const showConfirmModal = (type) => {
            return new Promise((resolve) => {
                const modal = components.Modal.create({
                    title: `connection.${type}Modal.title`,
                    className: `adl-confirm-modal`,
                    content: [generator.createElement('p', '', `connection.${type}Modal.message`)],
                    footer: true,
                    buttons: [
                        {
                            type: 'secondary',
                            label: 'general.cancel',
                            onClick: () => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            type: 'primary',
                            className: 'adlm--destructive',
                            label: 'general.confirm',
                            onClick: () => {
                                modal.close();
                                resolve(true);
                            }
                        }
                    ]
                });

                modal.open();
            });
        };

        const handleDisconnect = () => {
            utilities.showLoader();
            api.delete(configuration.disconnectUrl)
                .then(() => {
                    window.location.reload();
                })
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Displays the flash message.
         *
         * @param {string} message Translation key or message
         * @param {'success' | 'error'} status
         */
        const showFlashMessage = (message, status = 'success') => {
            const container = form?.querySelector('.adlp-flash-message-wrapper');
            if (!container) {
                return;
            }

            templateService.clearComponent(container);
            container.append(utilities.createFlashMessage(message, status));
            container.scrollIntoView({ behavior: 'smooth' });
        };

        /**
         * Handles footer visibility state.
         */
        const renderFooterState = () => {
            numberOfChanges = 0;

            Object.entries(changedSettings).forEach(([prop, value]) => {
                if (prop !== 'merchants' && activeSettings[prop] !== value) {
                    numberOfChanges++;
                }
            });

            utilities.renderFooterState(numberOfChanges);
        };
    }

    AdyenFE.ConnectionController = ConnectionController;
})();
