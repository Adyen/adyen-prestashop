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
                <div class="payment_module collapser" data-toggle="collapse"
                     data-target="#collapse{$storedPaymentApiId|escape:'html':'UTF-8'}" aria-expanded="true" aria-controls="collapseOne">
                    <span class="adyen-payment-method-label">
                        {l s='Pay with saved ' mod='adyenofficial'}{$name|escape:'html':'UTF-8'}
                        {l s=' ending: ' mod='adyenofficial'} {$number|escape:'html':'UTF-8'}
                    </span>
                    <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                          class="adyen-payment-form-{$storedPaymentApiId|escape:'html':'UTF-8'} additional-information" method="post">
                        <!-- Collapsable section -->
                        <div id="collapse{$storedPaymentApiId|escape:'html':'UTF-8'}" class="collapse">
                            <!-- Display payment errors -->
                            <div class="alert alert-danger error-container" role="alert"></div>
                            <!-- Display payment container -->
                            <div data-adyen-payment-container  class="adyen-payment-container"></div>
                            <div data-adyen-payment-error-container role="alert"></div>

                            <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                             {l s='Pay' mod='adyenofficial'} <i class="icon-chevron-right right"></i> </span></button>
                        </div>
                    </form>
                </div>
            </div>
        {else}
            <div class="col-xs-12 col-md-6">
                <div class="payment_module">
                    <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                          class="adyen-payment-form-{$storedPaymentApiId|escape:'html':'UTF-8'} additional-information" method="post">
                        <!-- Display payment errors -->
                        <div class="alert alert-danger error-container" role="alert"></div>
                        <!-- Display payment container -->
                        <div data-adyen-payment-container class="adyen-payment-container"></div>
                        <div data-adyen-payment-error-container role="alert"></div>
                    </form>
                </div>
            </div>
        {/if}
    </div>
{/if}
