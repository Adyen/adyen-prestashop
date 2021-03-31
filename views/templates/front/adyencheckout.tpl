{*
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *}

{if !$clientKey}
    {include './clientkey-error.tpl'}
{else}
    <div id="adyen-checkout-configuration"
         data-is-presta-shop16="{$isPrestaShop16|escape:'html':'UTF-8'}"
         data-locale="{$locale|escape:'html':'UTF-8'}"
         data-client-key="{$clientKey|escape:'html':'UTF-8'}"
         data-environment="{$environment|escape:'html':'UTF-8'}"
         data-payment-methods-response='{$paymentMethodsResponse|escape:'html':'UTF-8'}'
         data-is-user-logged-in="{$isUserLoggedIn|escape:'html':'UTF-8'}"
         data-payments-details-url="{$paymentsDetailsUrl|escape:'html':'UTF-8'}"
         data-payment-methods-configurations="{$paymentMethodsConfigurations|escape:'html':'UTF-8'}"

         data-currency-iso-code="{$currencyIsoCode|escape:'html':'UTF-8'}"
         data-total-amount-in-minor-units="{$totalAmountInMinorUnits|escape:'html':'UTF-8'}"
         data-payment-methods-with-pay-button-from-component="{$paymentMethodsWithPayButtonFromComponent|escape:'html':'UTF-8'}"
         data-enable-stored-payment-methods="{$enableStoredPaymentMethods|escape:'html':'UTF-8'}"

            {if isset($selectedDeliveryAddressId)}
                data-selected-delivery-address-id="{$selectedDeliveryAddressId|escape:'html':'UTF-8'}"
            {/if}
            {if isset($selectedInvoiceAddressId)}
                data-selected-invoice-address-id="{$selectedInvoiceAddressId|escape:'html':'UTF-8'}"
            {/if}
            {if isset($selectedInvoiceAddress)}
                data-selected-invoice-address="{$selectedInvoiceAddress|escape:'html':'UTF-8'}"
            {/if}
    ></div>
    {if $isPrestaShop16}
        <div style="display:none">
            <div id="actionModal">
                <div id="actionContainer"></div>
            </div>
        </div>
    {else}
        <div id="actionModal" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">{l s='Authentication' mod='adyenofficial'}</h4>
                    </div>
                    <div class="modal-body">
                        <div id="actionContainer"></div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
    <script>
        var adyenCheckoutConfiguration = document.querySelector('#adyen-checkout-configuration').dataset;

        var IS_PRESTA_SHOP_16 = adyenCheckoutConfiguration.isPrestaShop16;
        var isUserLoggedIn = adyenCheckoutConfiguration.isUserLoggedIn;
        var paymentsDetailsUrl = adyenCheckoutConfiguration.paymentsDetailsUrl;
        var selectedDeliveryAddressId = adyenCheckoutConfiguration.selectedDeliveryAddressId;
        var selectedInvoiceAddressId = adyenCheckoutConfiguration.selectedInvoiceAddressId;
        var selectedInvoiceAddress = JSON.parse(adyenCheckoutConfiguration.selectedInvoiceAddress);
        var paymentMethodsConfigurations = JSON.parse(adyenCheckoutConfiguration.paymentMethodsConfigurations);
        var paymentMethodsWithPayButtonFromComponent = JSON.parse(adyenCheckoutConfiguration.paymentMethodsWithPayButtonFromComponent);
        const enableStoredPaymentMethods = adyenCheckoutConfiguration.enableStoredPaymentMethods;

        var currencyIsoCode = adyenCheckoutConfiguration.currencyIsoCode;
        var totalAmountInMinorUnits = adyenCheckoutConfiguration.totalAmountInMinorUnits;

        var ADYEN_CHECKOUT_CONFIG = {
            locale: adyenCheckoutConfiguration.locale,
            clientKey: adyenCheckoutConfiguration.clientKey,
            environment: adyenCheckoutConfiguration.environment,
            showPayButton: false,
            paymentMethodsResponse: JSON.parse(adyenCheckoutConfiguration.paymentMethodsResponse)
        };

        // Translated texts
        const isNotAvailableText = "{l s=' is not available' js=1 mod='adyenofficial'}";
        const placeOrderErrorRequiredConditionsText = "{l s='The order cannot be placed. Please make sure you accepted all the required conditions.' js=1 mod='adyenofficial'}";
        const placeOrderInfoRequiredConditionsText = "{l s='Accept the required conditions which may be visible at the bottom of the page.' js=1 mod='adyenofficial'}";
        const placeOrderInfoInProgressText = "{l s='Placing order is in progress' js=1 mod='adyenofficial'}";
        const totalText = "{l s='total' js=1 mod='adyenofficial'}";
    </script>
{/if}
