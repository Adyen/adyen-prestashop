if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * @typedef ShopNotification
     * @property {string} orderId
     * @property {string} paymentMethod
     * @property {string} severity
     * @property {string} dateAndTime
     * @property {string} message
     * @property {string} details
     */

    /**
     * @typedef ShopNotifications
     * @property {boolean} nextPageAvailable
     * @property {ShopNotification[]} notifications
     */

    /**
     * @typedef WebhookNotification
     * @property {string} orderId
     * @property {string} logo
     * @property {string} paymentMethod
     * @property {string} notificationID
     * @property {string} dateAndTime
     * @property {string} code
     * @property {boolean} successful
     * @property {string} status
     * @property {boolean} hasDetails
     * @property {{reason: string, failureDescription: string, adyenLink: string, shopLink: string}?} details
     */

    /**
     * @typedef WebhookNotifications
     * @property {boolean} nextPageAvailable
     * @property {WebhookNotification[]} notifications
     */

    /**
     * Handles notification pages logic.
     *
     * @param {{
     * getShopEventsNotifications: string,
     * getWebhookEventsNotifications : string,
     * page: string}} configuration
     * @constructor
     */
    function NotificationsController(configuration) {
        /** @type AjaxServiceType */
        const api = AdyenFE.ajaxService;

        const { templateService, elementGenerator: generator, utilities, components } = AdyenFE;
        const dataTableComponent = components.DataTable;

        /** @type string */
        let currentStoreId = '';

        let nextPageAvailable = true;
        let currentlyLoading = false;
        let page = 1;
        const limit = 10;

        /**
         * Displays page content.
         *
         * @param {{state?: string, storeId: string}} config
         */
        this.display = ({ storeId }) => {
            currentStoreId = storeId;
            templateService.clearMainPage();

            configuration.getShopEventsNotifications = configuration.getShopEventsNotifications.replace(
                '{storeId}',
                storeId
            );
            configuration.getWebhookEventsNotifications = configuration.getWebhookEventsNotifications.replace(
                '{storeId}',
                storeId
            );

            return renderPage();
        };

        /**
         * Sets the unsaved changes.
         *
         * @return {boolean}
         */
        this.hasUnsavedChanges = () => false;

        const renderPage = () => {
            utilities.showLoader();
            let url;
            let renderer;

            templateService.clearMainPage();

            switch (configuration.page) {
                case 'shop':
                    url = `${configuration.getShopEventsNotifications}&page=${page}&limit=${limit}`;
                    renderer = renderShopNotificationsTable;
                    break;
                case 'webhook':
                    url = `${configuration.getWebhookEventsNotifications}&page=${page}&limit=${limit}`;
                    renderer = renderWebhookNotificationsTable;
                    break;
            }

            return api
                .get(url, () => {})
                .then(renderer)
                .finally(() => {
                    utilities.hideLoader();
                });
        };

        /**
         * Renders a modal to display notification details.
         *
         * @param {WebhookNotification} webhookNotification
         */
        const renderDetailsModal = (webhookNotification) => {
            const modal = components.Modal.create({
                title: 'notifications.webhook.notificationDetailsModal.title',
                className: 'adl-webhook-notifications-modal',
                content: [
                    generator.createElement('p', 'adlp-reason', '', null, [
                        generator.createElement(
                            'span',
                            'adlp-reason-title',
                            'notifications.webhook.notificationDetailsModal.reason'
                        ),
                        generator.createElement('span', 'adlp-reason-text', webhookNotification.details.reason)
                    ]),
                    generator.createElement('p', 'adlp-failure-description', '', null, [
                        generator.createElement(
                            'span',
                            'adlp-failure-description-title',
                            'notifications.webhook.notificationDetailsModal.failureDescription'
                        ),
                        generator.createElement(
                            'span',
                            'adlp-failure-description-text',
                            webhookNotification.details.failureDescription
                        )
                    ]),
                    generator.createElement(
                        'a',
                        'adlp-adyen-link',
                        '',
                        { href: webhookNotification.details.adyenLink, target: '_blank' },
                        [
                            generator.createElement(
                                'span',
                                '',
                                'notifications.webhook.notificationDetailsModal.paymentLink'
                            )
                        ]
                    ),
                    generator.createElement(
                        'a',
                        'adlp-shop-link',
                        '',
                        { href: webhookNotification.details.shopLink, target: '_blank' },
                        [generator.createElement('span', '', 'notifications.webhook.notificationDetailsModal.shopLink')]
                    )
                ],
                footer: true,
                canClose: true,
                buttons: [
                    {
                        type: 'primary',
                        label: 'general.ok',
                        onClick: () => modal.close()
                    }
                ]
            });

            modal.open();
        };

        /**
         * Renders shop table rows.
         *
         * @param {ShopNotification[]} shopNotifications
         * @returns {TableCell[][]}
         */
        const getRowsConfig = (shopNotifications) => {
            return shopNotifications?.map((notification) => {
                return [
                    {
                        label: notification.orderId,
                        className: 'adlm--left-aligned'
                    },
                    {
                        label: notification.paymentMethod,
                        className: 'adlm--left-aligned adlm--blue-text'
                    },
                    {
                        renderer: (cell) =>
                            cell.append(
                                generator.createElement(
                                    'span',
                                    `adlp-status adlt--${notification.severity}`,
                                    `notifications.shop.severity.${notification.severity}`
                                )
                            ),
                        className: 'adlm--left-aligned'
                    },
                    {
                        label: notification.dateAndTime,
                        className: 'adlm--left-aligned'
                    },
                    {
                        label: `notifications.shop.` + notification.message,
                        className: 'adlm--left-aligned'
                    },
                    {
                        label: `notifications.shop.` + notification.details,
                        className: 'adlm--left-aligned'
                    }
                ];
            });
        };

        /**
         * Renders webhook table rows.
         *
         * @param {WebhookNotification[]} webhookNotifications
         * @returns {TableCell[][]}
         */
        const getWebhookRowsConfig = (webhookNotifications) => {
            return webhookNotifications?.map((notification) => {
                const options = {
                    day: 'numeric',
                    month: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: false
                };

                const formattedDateTime = new Date(notification.dateAndTime)
                    .toLocaleString('en-US', options)
                    .replace(/, /g, ' ')
                    .replace(/\//g, '-');

                return [
                    {
                        label: notification.orderId,
                        className: 'adlm--left-aligned'
                    },
                    {
                        className: 'adlm--left-aligned',
                        renderer: (cell) =>
                            cell.prepend(
                                generator.createElement('img', 'adlp-payment-logo', '', { src: notification.logo })
                            )
                    },
                    {
                        label: notification.paymentMethod,
                        className: 'adlm--left-aligned adlm--blue-text'
                    },
                    {
                        label: notification.code,
                        className: 'adlm--left-aligned'
                    },
                    {
                        label: formattedDateTime,
                        className: 'adlm--left-aligned'
                    },
                    {
                        label: notification.successful ? 'general.yes' : 'general.no',
                        className: 'adlm--left-aligned'
                    },
                    {
                        renderer: (cell) =>
                            cell.append(
                                generator.createElement(
                                    'span',
                                    `adlp-status adlt--${notification.status}`,
                                    `notifications.webhook.status.${notification.status}`
                                )
                            ),
                        className: 'adlm--left-aligned'
                    },
                    notification.status === 'failed'
                        ? {
                              className: 'adlm--left-aligned',
                              renderer: (cell) =>
                                  cell.append(
                                      generator.createButton({
                                          type: 'primary',
                                          className: 'adl-button adlt--ghost adlm--blue',
                                          label: 'general.viewDetails',
                                          onClick: () => renderDetailsModal(notification)
                                      })
                                  )
                          }
                        : {}
                ];
            });
        };

        /**
         * Renders the shop notifications table.
         *
         * @param {ShopNotifications} shopNotificationsPage
         */
        const renderShopNotificationsTable = (shopNotificationsPage) => {
            const headers = [
                'notifications.shop.shopEventsNotifications.orderId',
                'notifications.shop.shopEventsNotifications.paymentMethod',
                'notifications.shop.shopEventsNotifications.severity',
                'notifications.shop.shopEventsNotifications.dateAndTime',
                'notifications.shop.shopEventsNotifications.message',
                'notifications.shop.shopEventsNotifications.details'
            ];

            createNotifications(headers, getRowsConfig, 'Shop', shopNotificationsPage);
        };

        /**
         * Renders the webhook notifications table.
         *
         * @param {WebhookNotifications} webhookNotificationsPage
         */
        const renderWebhookNotificationsTable = (webhookNotificationsPage) => {
            const headers = [
                'notifications.webhook.webhookEventsNotifications.orderId',
                'notifications.webhook.webhookEventsNotifications.logo',
                'notifications.webhook.webhookEventsNotifications.paymentMethod',
                'notifications.webhook.webhookEventsNotifications.eventCode',
                'notifications.webhook.webhookEventsNotifications.dateAndTime',
                'notifications.webhook.webhookEventsNotifications.success',
                'notifications.webhook.webhookEventsNotifications.status',
                'notifications.webhook.webhookEventsNotifications.action'
            ];
            createNotifications(headers, getWebhookRowsConfig, 'Webhook', webhookNotificationsPage);
        };

        /**
         * Returns a function that renders a notifications table and handles pagination.
         *
         * @param {string[]} headers The table headers.
         * @param {(notifications: any[]) => TableCell[][]} getRowsConfig A function that maps notifications to table
         *     rows.
         * @param {string} type The type of notifications.
         * @param {ShopNotifications | WebhookNotifications} notificationsPage Notifications page.
         */
        const createNotifications = (headers, getRowsConfig, type, notificationsPage) => {
            const typeLc = type.toLowerCase();
            nextPageAvailable = notificationsPage.nextPageAvailable;
            page = 1;
            currentlyLoading = false;

            const headerCells = headers.map((headerLabel) => ({
                label: headerLabel,
                className: 'adlm--left-aligned'
            }));

            const rows = getRowsConfig(notificationsPage.notifications);

            templateService
                .getMainPage()
                .append(
                    generator.createElement('div', `adl-notifications-page`, '', null, [
                        generator.createElement('h2', '', `notifications.${typeLc}.title`),
                        generator.createElement('p', '', `notifications.${typeLc}.description`),
                        rows.length
                            ? dataTableComponent.createDataTable(headerCells, rows, `adl-notifications-table`)
                            : dataTableComponent.createNoItemsMessage(`notifications.${typeLc}.noNotificationsMessage`)
                    ])
                );

            const tableWrapper = document.querySelector(
                `.adl-notifications-page .adl-notifications-table .adl-table-wrapper`
            );

            tableWrapper?.addEventListener('scroll', (event) => {
                if (
                    nextPageAvailable &&
                    !currentlyLoading &&
                    tableWrapper.scrollTop + tableWrapper.clientHeight > tableWrapper.scrollHeight - 10
                ) {
                    page++;
                    currentlyLoading = true;

                    let spinnerWrapper = generator.createElement('div', 'adl-loader adlt--large', '', '', [
                        generator.createElement('div', 'adlp-spinner')
                    ]);
                    tableWrapper.append(spinnerWrapper);

                    api.get(`${configuration[`get${type}EventsNotifications`]}&page=${page}&limit=${limit}`, () => null)
                        .then((newPage) => {
                            nextPageAvailable = newPage?.nextPageAvailable;

                            !!newPage?.notifications?.length &&
                                dataTableComponent.createTableRows(event.target, getRowsConfig(newPage.notifications));
                        })
                        .catch(console.error)
                        .finally(() => {
                            spinnerWrapper.remove();
                            currentlyLoading = false;
                        });
                }
            });
        };
    }

    AdyenFE.NotificationsController = NotificationsController;
})();
