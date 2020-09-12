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

{if !$originKey}
    {include './originkey-error.tpl'}
{else}
    <div id="adyen-checkout-configuration"
         data-is-presta-shop16="{$isPrestaShop16|escape:'html':'UTF-8'}"
         data-locale="{$locale|escape:'html':'UTF-8'}"
         data-origin-key="{$originKey|escape:'html':'UTF-8'}"
         data-environment="{$environment|escape:'html':'UTF-8'}"
         data-payment-methods-response='{$paymentMethodsResponse|escape:'html':'UTF-8'}'
         data-is-user-logged-in="{$isUserLoggedIn|escape:'html':'UTF-8'}"
         data-three-ds-process-url="{$threeDSProcessUrl|escape:'html':'UTF-8'}"
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
        var IS_PRESTA_SHOP_16 = ('true' === adyenCheckoutConfiguration.isPrestaShop16.toLowerCase());
        var isUserLoggedIn = adyenCheckoutConfiguration.isUserLoggedIn;
        var threeDsProcessUrl = adyenCheckoutConfiguration.threeDsProcessUrl;

        var ADYEN_CHECKOUT_CONFIG = {
            locale: adyenCheckoutConfiguration.locale,
            originKey: adyenCheckoutConfiguration.originKey,
            environment: adyenCheckoutConfiguration.environment,
            showPayButton: false,
            paymentMethodsResponse: JSON.parse(adyenCheckoutConfiguration.paymentMethodsResponse)
        };
    </script>
{/if}
