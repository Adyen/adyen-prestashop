{if !$originKey}
    <form id="payment-form" method="post">
        <h5>There is an error with retrieving the originKey,
            please check your API key in the Adyen Module configuration</h5>
    </form>
{else}
    <div class="row {$paymentMethodType|escape:'html'}"
         data-local-payment-method="{$paymentMethodType|escape:'html'}"
    >
        <div class="adyen-payment-method-label">{l s='Pay with ' mod='adyen'}{$paymentMethodName}</div>
        <form action="{$paymentProcessUrl}" method="post">
            <div data-adyen-payment-container></div>
            <div data-adyen-payment-error-container role="alert"></div>
            <input type="hidden" name="adyen-payment-issuer">
            <input type="hidden" name="adyen-payment-type" value="{$paymentMethodType}">
            {if $renderPayButton}
                <button type="submit" class="button btn btn-default standard-checkout button-medium">
                    <span>{l s='Pay' mod='adyen'} <i class="icon-chevron-right right"></i> </span>
                </button>
            {/if}
        </form>
    </div>
{/if}
