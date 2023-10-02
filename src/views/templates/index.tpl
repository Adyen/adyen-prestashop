<!DOCTYPE html>
<!--suppress HtmlUnknownAnchorTarget, HtmlUnknownTarget -->
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Adyen admin FE</title>
</head>
<body>
<!-- This is a main placeholder that should be used in all integrations -->
<div id="adl-page" class="adl-page">
    <aside class="adl-sidebar"></aside>
    <main>
        <div class="adlp-content-holder">
            <header id="adl-main-header">
                <div class="adl-header-navigation">
                    <ul class="adlp-nav-list">
                        <li class="adlp-nav-item adlm--merchant adls--hidden"></li>
                        <li class="adlp-nav-item" id="adl-store-switcher"></li>
                        <li class="adlp-nav-item adlm--mode adls--hidden"></li>
                        <li class="adlp-nav-item adlm--download adls--hidden"></li>
                    </ul>
                </div>
                <div class="adl-header-holder" id="adl-header-section"></div>
            </header>
            <main id="adl-main-page-holder"></main>
        </div>
    </main>
    <div class="adl-page-loader adls--hidden" id="adl-spinner">
        <div class="adl-loader adlt--large">
            <span class="adlp-spinner"></span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        AdyenFE.translations = {
            default: {$translations.default|json_encode},
            current: {$translations.current|json_encode}
        };

        AdyenFE.utilities.showLoader();

        const pageConfiguration = {
            connection: {
                getSettingsUrl: '{$urls.connection.getSettingsUrl}',
                submitUrl: '{$urls.connection.submitUrl}',
                disconnectUrl: '{$urls.connection.disconnectUrl}',
                getMerchantsUrl: '{$urls.connection.getMerchantsUrl}',
                validateConnectionUrl: '{$urls.connection.validateConnectionUrl}',
                validateWebhookUrl: '{$urls.connection.validateWebhookUrl}'
            },
            payments: {
                getConfiguredPaymentsUrl: '{$urls.payments.getConfiguredPaymentsUrl}',
                addMethodConfigurationUrl: '{$urls.payments.addMethodConfigurationUrl}',
                getMethodConfigurationUrl: '{$urls.payments.getMethodConfigurationUrl}',
                saveMethodConfigurationUrl: '{$urls.payments.saveMethodConfigurationUrl}',
                getAvailablePaymentsUrl: '{$urls.payments.getAvailablePaymentsUrl}',
                deleteMethodConfigurationUrl: '{$urls.payments.deleteMethodConfigurationUrl}'
            },
            settings: {
                getShippingStatusesUrl: '{$urls.settings.getShippingStatusesUrl}',
                getSettingsUrl: '{$urls.settings.getSettingsUrl}',
                saveSettingsUrl: '{$urls.settings.saveSettingsUrl}',
                getOrderMappingsUrl: '{$urls.settings.getOrderMappingsUrl}',
                saveOrderMappingsUrl: '{$urls.settings.saveOrderMappingsUrl}',
                getGivingUrl: '{$urls.settings.getGivingUrl}',
                saveGivingUrl: '{$urls.settings.saveGivingUrl}',
                getSystemInfoUrl: '{$urls.settings.getSystemInfoUrl}',
                saveSystemInfoUrl: '{$urls.settings.saveSystemInfoUrl}',
                downloadWebhookReportUrl: '{$urls.settings.downloadWebhookReportUrl}',
                downloadIntegrationReportUrl: '{$urls.settings.downloadIntegrationReportUrl}',
                downloadSystemInfoFileUrl: '{$urls.settings.downloadSystemInfoFileUrl}',
                webhookValidationUrl: '{$urls.settings.webhookValidationUrl}',
                integrationValidationUrl: '{$urls.settings.integrationValidationUrl}',
                integrationValidationTaskCheckUrl: '{$urls.settings.integrationValidationTaskCheckUrl}'
            },
            notifications: {
                getShopEventsNotifications: '{$urls.notifications.getShopEventsNotifications}',
                getWebhookEventsNotifications: '{$urls.notifications.getWebhookEventsNotifications}'
            }
        };

        AdyenFE.state = new AdyenFE.StateController({
            storesUrl: '{$urls.stores.storesUrl}',
            connectionDetailsUrl: '{$urls.connection.getSettingsUrl}',
            merchantsUrl: '{$urls.connection.getMerchantsUrl}',
            currentStoreUrl: '{$urls.stores.currentStoreUrl}',
            switchContextUrl: '{$urls.stores.switchContextUrl}',
            stateUrl: '{$urls.integration.stateUrl}',
            versionUrl: '{$urls.version.versionUrl}',
            downloadVersionUrl: 'https://logeecom.com/wp-content/uploads/2016/09/logo-white.png',
            pageConfiguration: pageConfiguration,
            templates: {
                'sidebar': {$sidebar|json_encode}
            }
        });

        AdyenFE.state.display();
        AdyenFE.utilities.hideLoader();
    });
</script>
</body>
</html>
