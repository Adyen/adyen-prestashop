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
        {if $isPrestaShop16}
        <div class="col-xs-12 col-md-12">
            <div class="payment_module adyen-collapse-styling" style="background-image: url(https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/medium/{$paymentMethodBrand|escape:'html':'UTF-8'}.png)">
                {* If collapsing is enabled by config *}
                <span {if $collapsePayments} class="adyen-payment-method-label adyen-collapser collapsed" data-toggle="collapse" data-target="#collapse{$paymentMethodType|escape:'html':'UTF-8'}" aria-expanded="false" aria-controls="collapse{$paymentMethodType|escape:'html':'UTF-8'}"
                        {else} class="adyen-payment-method-label" {/if}>
                    {l s='Pay by %s' sprintf=[{$paymentMethodName|escape:'html':'UTF-8'}] mod='adyenofficial'}
                </span>
                <div class="row">
                    <div class="col-xs-12 col-md-6">
                        <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                              class="adyen-payment-form-{$paymentMethodType|escape:'html':'UTF-8'} additional-information" method="post">
                            {* If collapsing is enabled by config *}
                            <div {if $collapsePayments} id="collapse{$paymentMethodType|escape:'html':'UTF-8'}" class="adyen-collapsable collapse" {/if}>
                                <div data-adyen-payment-container></div>
                                {* Display payment extra info *}
                                <div class="alert alert-info info-container" role="alert"></div>
                                {* Display payment errors *}
                                <div class="alert alert-danger error-container" role="alert"></div>
                                <div data-adyen-payment-error-container role="alert"></div>
                                {if !in_array($paymentMethodType, $paymentMethodsWithPayButtonFromComponent|json_decode:1)}
                                    <button type="submit" class="button btn btn-default standard-checkout button-medium">
                                        <span>{l s='Pay' mod='adyenofficial'} <i class="icon-chevron-right right"></i></span>
                                    </button>
                                {/if}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        {else}
        <div class="col-xs-12 col-md-6">
            <div class="payment_module">
                <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                      class="adyen-payment-form-{$paymentMethodType|escape:'html':'UTF-8'} additional-information" method="post">
                    <div data-adyen-payment-container></div>
                    {* Display payment extra info *}
                    <div class="alert alert-info info-container" role="alert"></div>
                    {* Display payment errors *}
                    <div class="alert alert-danger error-container" role="alert"></div>
                    <div data-adyen-payment-error-container role="alert"></div>
                </form>
            </div>
        </div>
        {/if}
    </div>
{/if}
