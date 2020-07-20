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
    ></div>
    <script>
        var adyenCheckoutConfiguration = document.querySelector('#adyen-checkout-configuration').dataset;
        var IS_PRESTA_SHOP_16 = ('true' === adyenCheckoutConfiguration.isPrestaShop16.toLowerCase());

        var ADYEN_CHECKOUT_CONFIG = {
            locale: adyenCheckoutConfiguration.locale,
            originKey: adyenCheckoutConfiguration.originKey,
            environment: adyenCheckoutConfiguration.environment,
            showPayButton: false,
            paymentMethodsResponse: JSON.parse(adyenCheckoutConfiguration.paymentMethodsResponse)
        };
    </script>
{/if}
