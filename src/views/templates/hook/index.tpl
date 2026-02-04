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
                getSettingsUrl: '{$urls.connection.getSettingsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                submitUrl: '{$urls.connection.submitUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                disconnectUrl: '{$urls.connection.disconnectUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getMerchantsUrl: '{$urls.connection.getMerchantsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                validateConnectionUrl: '{$urls.connection.validateConnectionUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                validateWebhookUrl: '{$urls.connection.validateWebhookUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}'
            },
            payments: {
                getConfiguredPaymentsUrl: '{$urls.payments.getConfiguredPaymentsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                addMethodConfigurationUrl: '{$urls.payments.addMethodConfigurationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getMethodConfigurationUrl: '{$urls.payments.getMethodConfigurationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                saveMethodConfigurationUrl: '{$urls.payments.saveMethodConfigurationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getAvailablePaymentsUrl: '{$urls.payments.getAvailablePaymentsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                deleteMethodConfigurationUrl: '{$urls.payments.deleteMethodConfigurationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}'
            },
            settings: {
                getShippingStatusesUrl: '{$urls.settings.getShippingStatusesUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getSettingsUrl: '{$urls.settings.getSettingsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                saveSettingsUrl: '{$urls.settings.saveSettingsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getOrderMappingsUrl: '{$urls.settings.getOrderMappingsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                saveOrderMappingsUrl: '{$urls.settings.saveOrderMappingsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getGivingUrl: '{$urls.settings.getGivingUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                saveGivingUrl: '{$urls.settings.saveGivingUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getSystemInfoUrl: '{$urls.settings.getSystemInfoUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                saveSystemInfoUrl: '{$urls.settings.saveSystemInfoUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                downloadWebhookReportUrl: '{$urls.settings.downloadWebhookReportUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                downloadIntegrationReportUrl: '{$urls.settings.downloadIntegrationReportUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                downloadSystemInfoFileUrl: '{$urls.settings.downloadSystemInfoFileUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                webhookValidationUrl: '{$urls.settings.webhookValidationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                integrationValidationUrl: '{$urls.settings.integrationValidationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                integrationValidationTaskCheckUrl: '{$urls.settings.integrationValidationTaskCheckUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                webhookReRegistrationUrl: '{$urls.settings.webhookReRegistrationUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            },
            notifications: {
                getShopEventsNotifications: '{$urls.notifications.getShopEventsNotifications|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
                getWebhookEventsNotifications: '{$urls.notifications.getWebhookEventsNotifications|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}'
            }
        };

        AdyenFE.state = new AdyenFE.StateController({
            storesUrl: '{$urls.stores.storesUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            connectionDetailsUrl: '{$urls.connection.getSettingsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            merchantsUrl: '{$urls.connection.getMerchantsUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            currentStoreUrl: '{$urls.stores.currentStoreUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            switchContextUrl: '{$urls.stores.switchContextUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            stateUrl: '{$urls.integration.stateUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            versionUrl: '{$urls.version.versionUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}',
            downloadVersionUrl: 'https://github.com/Adyen/adyen-prestashop/releases',
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
