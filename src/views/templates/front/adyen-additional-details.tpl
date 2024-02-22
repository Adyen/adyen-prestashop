{extends file='checkout/checkout.tpl'}

{block name='content'}
    {if !empty($action)}
        <div class="page-content page-order-confirmation card">
            <div class="card-block">
                <div class="row">
                    <div id="adyen-loader" class="adl-loader" style="display: none">
                        <span class="adlp-spinner">
                        </span>
                    </div>
                    {if !empty($action)}
                        <div
                                data-adyen-payment-action-container
                                data-adyen-payment-action="{$action|escape:'html':'UTF-8'}"
                        ></div>
                    {/if}
                    <div data-adyen-payment-error-container role="alert"></div>
                    <input type="hidden" id="adyen-redirect-action-url"
                           value="{$paymentRedirectActionURL|escape:'html':'UTF-8'}">
                    <input type="hidden" id="adyen-checkout-config-url"
                           value="{$checkoutConfigUrl|escape:'html':'UTF-8'}">
                    <input type="hidden" id="adyen-checkout-url" value="{$checkoutUrl|escape:'html':'UTF-8'}">
                </div>
            </div>
        </div>
    {/if}
{/block}