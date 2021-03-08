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
    <form method="post">
        {include './clientkey-error.tpl'}
    </form>
{else}
    <div class="row adyen-payment {$paymentMethodType|escape:'html':'UTF-8'}"
         data-local-payment-method="{$paymentMethodType|escape:'html':'UTF-8'}">
        <div class="col-xs-12 col-md-6">
            <div class="payment_module">
                <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                      class="adyen-payment-form-{$paymentMethodType|escape:'html':'UTF-8'}" method="post">
                    <div data-adyen-payment-container class="additional-information"></div>
                    <!-- Display payment extra info -->
                    <div class="alert alert-info info-container" role="alert"></div>
                    <!-- Display payment errors -->
                    <div class="alert alert-danger error-container" role="alert"></div>
                    <div data-adyen-payment-error-container role="alert"></div>
                    {if $isPrestaShop16 AND !in_array($paymentMethodType, $paymentMethodsWithPayButtonFromComponent|json_decode:1)}
                        <button type="submit" class="button btn btn-default standard-checkout button-medium">
                            <span>{l s='Pay' mod='adyenofficial'} <i class="icon-chevron-right right"></i> </span>
                        </button>
                    {/if}
                </form>
            </div>
        </div>
    </div>
{/if}
