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
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
{else}
    <div id="adyen-checkout-configuration"
         data-is-presta-shop-16="{$isPrestaShop16}"
         data-locale="{$locale}"
         data-origin-key="{$originKey}"
         data-environment="{$environment}"
         data-payment-methods-response="{$paymentMethodsResponse}"
    ></div>
    <script>
        var adyenCheckoutConfiguration = document.querySelector('#adyen-checkout-configuration').dataset;
        var IS_PRESTA_SHOP_16 = adyenCheckoutConfiguration.isPrestaShop16;

        var ADYEN_CHECKOUT_CONFIG = {
            locale: adyenCheckoutConfiguration.locale,
            originKey: adyenCheckoutConfiguration.originKey,
            environment: adyenCheckoutConfiguration.environment,
            showPayButton: false,
            paymentMethodsResponse: adyenCheckoutConfiguration.paymentMethodsResponse
        };
    </script>
{/if}