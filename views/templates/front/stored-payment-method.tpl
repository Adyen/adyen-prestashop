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
    <div class="row adyen-payment"
         data-stored-payment-api-id="{$storedPaymentApiId|escape:'html':'UTF-8'}">
        {if $isPrestaShop16}
            <div class="col-xs-12 col-md-12">
                <div class="payment_module adyen-collapse-styling" style="background-image: url(https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/medium/{$logoBrand|escape:'html':'UTF-8'}.png)">
                    {* If collapsing is enabled by config *}
                    <span {if $collapsePayments} class="adyen-collapser adyen-payment-method-label collapsed" data-toggle="collapse" data-target="#collapse{$number|escape:'html':'UTF-8'}" aria-expanded="false" aria-controls="collapse{$number|escape:'html':'UTF-8'}"
                            {else} class="adyen-payment-method-label" {/if}>
                        {l s='Pay by saved' mod='adyenofficial'} {$name|escape:'html':'UTF-8'}
                        {l s=' ending: ' mod='adyenofficial'} {$number|escape:'html':'UTF-8'}
                    </span>
                    <div class="row">
                        <div class="col-xs-12 col-md-6">
                            <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                                  class="adyen-payment-form-{$storedPaymentApiId|escape:'html':'UTF-8'}
                                  additional-information mb-0" method="post">
                                {* If collapsing is enabled by config *}
                                <div {if $collapsePayments} id="collapse{$number|escape:'html':'UTF-8'}" class="adyen-collapsable collapse" {/if}>
                                    {* Display payment errors *}
                                    <div class="alert alert-danger error-container" role="alert"></div>
                                    {* Display payment container *}
                                    <div data-adyen-payment-container  class="adyen-payment-container"></div>
                                    <div data-adyen-payment-error-container role="alert"></div>

                                    <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                                     {l s='Pay' mod='adyenofficial'} <i class="icon-chevron-right right"></i> </span></button>
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
                          class="adyen-payment-form-{$storedPaymentApiId|escape:'html':'UTF-8'} additional-information" method="post">
                        {* Display payment errors *}
                        <div class="alert alert-danger error-container" role="alert"></div>
                        {* Display payment container *}
                        <div data-adyen-payment-container class="adyen-payment-container"></div>
                        <div data-adyen-payment-error-container role="alert"></div>
                    </form>
                </div>
            </div>
        {/if}
    </div>
{/if}
