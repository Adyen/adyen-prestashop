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
    {if $prestashop16}
        <p></p>
        <div class="adyen-payment-method-label">
            {l s='Pay with Credit Card' mod='adyenofficial'}
        </div>
    {/if}
    <div class="row adyen-payment">
        <div class="col-xs-12 col-md-6">
            <form action="{$paymentProcessUrl|escape:'html':'UTF-8'}" class="adyen-payment-form" method="post"
                  data-is-logged-in-user="{$loggedInUser|escape:'html':'UTF-8'}"
                  data-three-ds-process-url="{$threeDSProcessUrl|escape:'html':'UTF-8'}"
            >

                <!-- Display payment errors -->
                <div class="alert alert-danger error-container" role="alert"></div>

                <div class="checkout-container" id="cardContainer"></div>
                <input type="hidden" name="redirectMethod"/>
                <input type="hidden" name="issuerUrl"/>
                <input type="hidden" name="paRequest"/>
                <input type="hidden" name="md"/>
                <input type="hidden" name="adyenMerchantReference">

                {if $prestashop16}
                    <div style="display:none">
                        <div id="threeDS2Modal">
                            <div id="threeDS2Container"></div>
                        </div>
                    </div>
                {else}
                    <div id="threeDS2Modal" class="modal fade" tabindex="-1" role="dialog">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="modal-title">{l s='Authentication' mod='adyenofficial'}</h4>
                                </div>
                                <div class="modal-body">
                                    <div id="threeDS2Container"></div>
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
