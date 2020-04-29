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
        <h5>There is an error with retrieving the originKey,
            please check your API key in the Adyen Module configuration</h5>
    </form>
{else}
    <div class="row adyen-payment"
         data-three-ds-process-url="{$threeDSProcessUrl|escape:'html'}"
         data-stored-payment-api-id="{$storedPaymentApiId|escape:'html'}"
    >
        <div class="col-xs-12 col-md-6">
            <form action="{$paymentProcessUrl|escape:'html'}" class="adyen-payment-form-{$storedPaymentApiId|escape:'html'}" method="post">

                <!-- Display payment errors -->
                <div class="alert alert-danger error-container" role="alert"></div>

                {if $prestashop16}
                    <p></p>
                    <div class="adyen-payment-method-label">
                        {l s='Pay with saved ' mod='adyenofficial'} {$name|escape:'html'}
                        {l s=' ending: ' mod='adyenofficial'} {$number|escape:'html'}
                    </div>
                {/if}

                <div class="checkout-container" id="cardContainer-{$storedPaymentApiId|escape:'html'}"></div>
                <input type="hidden" name="redirectMethod"/>
                <input type="hidden" name="issuerUrl"/>
                <input type="hidden" name="paRequest"/>
                <input type="hidden" name="md"/>
                <input type="hidden" name="adyenMerchantReference">

                {if $prestashop16}
                    <div style="display:none">
                        <div id="threeDS2Modal-{$storedPaymentApiId|escape:'html'}">
                            <div id="threeDS2Container-{$storedPaymentApiId|escape:'html'}"></div>
                        </div>
                    </div>
                {else}
                    <div id="threeDS2Modal-{$storedPaymentApiId|escape:'html'}" class="modal fade" tabindex="-1" role="dialog">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="modal-title">Authentication</h4>
                                </div>
                                <div class="modal-body">
                                    <div id="threeDS2Container-{$storedPaymentApiId|escape:'html'}"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}

                {if $prestashop16}
                    <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                             {l s='Pay' mod='adyenofficial'} <i class="icon-chevron-right right"></i> </span></button>
                {/if}
            </form>
        </div>
    </div>
{/if}
