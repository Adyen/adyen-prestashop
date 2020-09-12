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
    <form method="post">
        {include './originkey-error.tpl'}
    </form>
{else}
    <div class="row adyen-payment"
         data-stored-payment-api-id="{$storedPaymentApiId|escape:'html':'UTF-8'}"
    >
        <div class="col-xs-12 col-md-6">
            {if $isPrestaShop16}
            <div class="adyen-payment-method-label">
                {l s='Pay with saved ' mod='adyenofficial'} {$name|escape:'html':'UTF-8'}
                {l s=' ending: ' mod='adyenofficial'} {$number|escape:'html':'UTF-8'}
            </div>
            {/if}
            <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}"
                  class="adyen-payment-form-{$storedPaymentApiId|escape:'html':'UTF-8'}" method="post">
                <!-- Display payment errors -->
                <div class="alert alert-danger error-container" role="alert"></div>
                <div data-adyen-payment-container></div>
                <div data-adyen-payment-error-container role="alert"></div>
                {if $renderPayButton}
                    <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                         {l s='Pay' mod='adyenofficial'} <i class="icon-chevron-right right"></i> </span></button>
                {/if}
            </form>
        </div>
    </div>
{/if}
