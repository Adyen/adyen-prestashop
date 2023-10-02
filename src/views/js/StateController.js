if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * @typedef Store
     * @property {string} storeId
     * @property {string} storeName
     * @property {boolean} maintenanceMode
     */
    /**
     * @typedef Merchant
     * @property {string} merchantId
     * @property {string} merchantName
     */

    /**
     * @typedef StateConfiguration
     * @property {string?} pagePlaceholder
     * @property {string} stateUrl
     * @property {string} storesUrl
     * @property {string} currentStoreUrl
     * @property {string} connectionDetailsUrl
     * @property {string} merchantsUrl
     * @property {string} versionUrl
     * @property {string} downloadVersionUrl
     * @property {string?} systemId
     * @property {Record<string, any>} pageConfiguration
     * @property {Record<string, any>} templates
     */

    /**
     * Main controller of the application.
     *
     * @param {StateConfiguration} configuration
     *
     * @constructor
     */
    function StateController(configuration) {
        /** @type AjaxServiceType */
        const api = AdyenFE.ajaxService;

        const { pageControllerFactory, utilities, templateService, elementGenerator, translationService } = AdyenFE;

        let currentState = '';
        let previousState = '';
        let controller = null;

        /**
         * Main entry point for the application.
         * Initializes the sidebar.
         * Determines the current state and runs the start controller.
         */
        this.display = () => {
            utilities.showLoader();
            templateService.setTemplates(configuration.templates);
            templateService.clearMainPage();

            window.addEventListener('hashchange', updateStateOnHashChange, false);

            api.get(!getStoreId() ? configuration.currentStoreUrl : configuration.storesUrl, () => null, true)
                .then(
                    /** @param {Store|Store[]} response */
                    (response) => {
                        const loadStore = (store) => {
                            setStoreId(store.storeId);
                            setMaintenanceMode(store.maintenanceMode);

                            return Promise.all([displayPageBasedOnState(), this.setHeader()]);
                        };

                        let store;
                        if (!Array.isArray(response)) {
                            store = response;
                        } else {
                            store = response.find((s) => s.storeId === getStoreId());
                        }

                        if (!store) {
                            // the active store is probably deleted, we need to switch to the default store
                            return api.get(configuration.currentStoreUrl, null, true).then(loadStore);
                        }

                        return loadStore(store);
                    }
                )
                .catch(() => {
                    initializeSidebar();
                    this.disableSidebar();
                    return this.setHeader();
                })
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Navigates to a state.
         *
         * @param {string} state
         * @param {Record<string, any> | null?} additionalConfig
         * @param {boolean} [force=false]
         */
        this.goToState = (state, additionalConfig = null, force = false) => {
            if (currentState === state && !force) {
                return;
            }

            window.location.hash = state;

            const config = {
                storeId: getStoreId(),
                ...(additionalConfig || {})
            };

            const sidebar = document.querySelector('#adl-page .adl-sidebar');
            if (!window.location.hash.startsWith('#settings')) {
                sidebar && utilities.hideElement(sidebar.querySelector('.adlp-quick-links'));
            } else {
                sidebar && utilities.showElement(sidebar.querySelector('.adlp-quick-links'));
            }

            const [controllerName, page, stateParam] = state.split('-');
            controller = pageControllerFactory.getInstance(
                controllerName,
                getControllerConfiguration(controllerName, page, stateParam)
            );

            if (controller) {
                controller.display(config);
            }

            previousState = currentState;
            currentState = state;
        };

        /**
         * Enables the sidebar.
         */
        this.enableSidebar = () => {
            document
                .querySelectorAll('.adl-sidebar .adlp-menu-item')
                .forEach((item) => item.classList.remove('adls--disabled'));
        };

        /**
         * Disables the sidebar.
         */
        this.disableSidebar = () => {
            document
                .querySelectorAll('.adl-sidebar .adlp-menu-item:not(.adlt--connection)')
                .forEach((item) => item.classList.add('adls--disabled'));
        };

        /**
         * Updates the main header.
         *
         * @returns {Promise<void>}
         */
        this.setHeader = () => {
            return Promise.all([setStoresSwitcher(), setConnectionData()]);
        };

        const updateStateOnHashChange = () => {
            const state = window.location.hash.substring(1);
            if (state) {
                this.goToState(state);
                updateSidebarState();
            }

            getSidebar().classList.remove('adls--menu-active');
        };

        /**
         * Selects active sidebar item based on the location hash.
         */
        const updateSidebarState = () => {
            const sidebar = getSidebar();
            sidebar.querySelectorAll('.adlp-menu-item a').forEach((el) => el.classList.remove('adls--active'));
            sidebar.querySelector(`[href="${location.hash}"]`)?.classList.add('adls--active');
        };

        /**
         * Gets the sidebar DOM element.
         *
         * @returns {HTMLElement}
         */
        const getSidebar = () => {
            return document.querySelector('#adl-page .adl-sidebar');
        };

        /**
         * Handles version download.
         *
         * @param {string} latest
         */
        const setDownloadVersion = (latest) => {
            if (configuration.downloadVersionUrl) {
                const downloadVersionBox = document.querySelector(
                    '#adl-page .adl-header-navigation .adlp-nav-list .adlp-nav-item.adlm--download'
                );
                const title = translationService.translate('mainHeader.download');

                downloadVersionBox.classList.remove('adls--hidden');
                templateService.clearComponent(downloadVersionBox);
                downloadVersionBox.append(
                    elementGenerator.createElement(
                        'a',
                        'adlp-download-link',
                        '',
                        { href: configuration.downloadVersionUrl, target: '_blank' },
                        [
                            elementGenerator.createElement('span', 'adlp-nav-item-icon adlm--download'),
                            elementGenerator.createElement('div', 'adlp-nav-item-text', '', null, [
                                elementGenerator.createElement('h3', 'adlp-nav-item-title', title),
                                elementGenerator.createElement('span', 'adlp-nav-item-subtitle', latest)
                            ])
                        ]
                    )
                );
            }
        };

        /**
         * Renders a confirmation modal for a store change when there are unsaved changes.
         */
        const renderSwitchToStoreModal = () => {
            return new Promise((resolve) => {
                const modal = AdyenFE.components.Modal.create({
                    title: 'payments.switchToStore.title',
                    content: [AdyenFE.elementGenerator.createElement('span', '', 'payments.switchToStore.description')],
                    footer: true,
                    canClose: false,
                    buttons: [
                        {
                            type: 'secondary',
                            label: 'general.back',
                            onClick: () => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            type: 'primary',
                            className: 'adlt--primary',
                            label: 'general.yes',
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

        /**
         * Sets the store switcher in the main header.
         */
        const setStoresSwitcher = () => {
            return api.get(configuration.storesUrl, null, true).then(
                /** @param {Store[]} stores */
                (stores) => {
                    if (!stores?.length) {
                        return;
                    }

                    const storesData = stores.map((store) => ({
                        label: store.storeName,
                        value: store.storeId
                    }));
                    const elem = document.getElementById('adl-store-switcher');
                    templateService.clearComponent(elem);
                    elem.append(
                        elementGenerator.createStoreSwitcher(
                            storesData,
                            '',
                            'mainHeader.switchStore',
                            getStoreId(),
                            () => {
                                if (controller !== null && controller.hasUnsavedChanges()) {
                                    return renderSwitchToStoreModal();
                                }

                                return Promise.resolve(true);
                            },
                            (storeId) => {
                                setStoreId(storeId);
                                window.location.hash = '';
                                this.enableSidebar();
                                this.display();
                            }
                        )
                    );
                    elem.classList.remove('adls--hidden');
                }
            );
        };

        /**
         * Updated the connection data in the main header.
         *
         * @returns {Promise<any>}
         */
        const setConnectionData = () => {
            return api
                .get(configuration.connectionDetailsUrl.replace('{storeId}', getStoreId()), () => null, true)
                .then(
                    /** @param {Connection} connection */
                    (connection) => {
                        const modeElem = document.querySelector('.adlp-nav-item.adlm--mode');
                        const merchantElem = document.querySelector('.adlp-nav-item.adlm--merchant');
                        const data = connection?.mode === 'test' ? connection?.testData : connection?.liveData;
                        if (!data?.apiKey) {
                            modeElem.classList.add('adls--hidden');
                            merchantElem.classList.add('adls--hidden');
                            return;
                        }

                        templateService.clearComponent(modeElem);
                        modeElem.classList.remove('adls--hidden');
                        modeElem.append(
                            ...elementGenerator.createHeaderItem(
                                'mainHeader.mode',
                                'connection.environment.options.' + connection.mode
                            )
                        );

                        return api
                            .get(configuration.merchantsUrl.replace('{storeId}', getStoreId()), () => [], true)
                            .then(
                                /** @param {Merchant[]} merchants} */
                                (merchants) => {
                                    const merchantName = merchants.find(
                                        (m) => m.merchantId === data.merchantId
                                    )?.merchantName;
                                    templateService.clearComponent(merchantElem);
                                    if (!merchantName) {
                                        merchantElem.classList.add('adls--hidden');
                                    } else {
                                        merchantElem.classList.remove('adls--hidden');
                                    }

                                    merchantElem.append(
                                        ...elementGenerator.createHeaderItem('mainHeader.merchant', merchantName)
                                    );
                                }
                            );
                    }
                );
        };

        /**
         * Display the maintenance mode message.
         *
         * @param {boolean} enabled If a message should be displayed.
         */
        const setMaintenanceMode = (enabled) => {
            const container = templateService.getHeaderSection();
            templateService.clearComponent(container);
            enabled &&
                container.append(
                    elementGenerator.createFlashMessage(['maintenance.title', 'maintenance.description'], 'warning')
                );
        };

        /**
         * Initializes the sidebar.
         */
        const initializeSidebar = () => {
            const sidebar = getSidebar();

            if (sidebar) {
                sidebar.innerHTML = templateService.getTemplate('sidebar');
                const versionInfo = sidebar.querySelector('.adlp-version-info');
                versionInfo.classList.add('adls--hidden');

                // initialize version
                api.get(configuration.versionUrl, null, true)
                    .then((version) => {
                        versionInfo.classList.remove('adls--hidden');
                        versionInfo.innerHTML = version.installed;

                        if (version.installed !== version.latest) {
                            versionInfo.classList.add('adls--warning');
                            versionInfo.classList.add('adl-hint');
                            versionInfo.append(
                                elementGenerator.createElement(
                                    'span',
                                    'adlp-tooltip adlt--bottom',
                                    'sidebar.newVersionAvailable'
                                )
                            );
                            setDownloadVersion(version.latest);
                        }
                    })
                    .catch(() => {
                        // probably not implemented yet
                        sidebar.querySelector('.adlp-version-info').remove();
                    });

                sidebar.querySelector('.adlp-mobile-menu').addEventListener('click', () => {
                    sidebar.classList.toggle('adls--menu-active');
                });
                sidebar.querySelector('.adlp-mobile-underlay').addEventListener('click', () => {
                    sidebar.classList.remove('adls--menu-active');
                });
                updateSidebarState();
            }
        };

        /**
         * Returns the current merchant state.
         *
         * @return {Promise<"onboarding" | "dashboard">}
         */
        this.getCurrentMerchantState = () => {
            return api
                .get(configuration.stateUrl.replace('{storeId}', getStoreId()), () => {})
                .then((response) => response?.state || 'onboarding');
        };

        /**
         * Opens a specific page based on the current state.
         */
        const displayPageBasedOnState = () => {
            initializeSidebar();
            return this.getCurrentMerchantState().then((state) => {
                // if user is logged in, go to payments
                switch (state) {
                    case 'onboarding':
                        this.disableSidebar();
                        api.get(configuration.connectionDetailsUrl.replace('{storeId}', getStoreId()), () => null).then(
                            (connection) => {
                                if (connection?.testData?.apiKey || connection?.liveData?.apiKey) {
                                    this.goToState('connection-merchant', null, true);
                                } else {
                                    this.goToState('connection', null, true);
                                }
                            }
                        );
                        break;
                    default:
                        this.goToState(window.location.hash.substring(1) || 'payments', null, true);
                        break;
                }
            });
        };

        /**
         * Gets controller configuration.
         *
         * @param {string} controllerName
         * @param {string?} page
         * @param {string?} stateParam
         * @return {Record<string, any>}}
         */
        const getControllerConfiguration = (controllerName, page, stateParam) => {
            let config = utilities.cloneObject(configuration.pageConfiguration[controllerName] || {});

            page && (config.page = page);
            stateParam && (config.stateParam = stateParam);

            return config;
        };

        /**
         * Sets the store ID.
         *
         * @param {string} storeId
         */
        const setStoreId = (storeId) => {
            sessionStorage.setItem('adl-active-store-id', storeId);
        };

        /**
         * Gets the store ID.
         *
         * @returns {string}
         */
        const getStoreId = () => {
            return sessionStorage.getItem('adl-active-store-id');
        };
    }

    AdyenFE.StateController = StateController;
})();
