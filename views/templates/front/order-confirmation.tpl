{include './adyencheckout.tpl'}

{if $action}
    <div class="row"">
        <div
            data-adyen-payment-action-container
            data-adyen-payment-action="{$action|escape:'html'}"
        ></div>
        <div data-adyen-payment-error-container role="alert"></div>
    </div>
{/if}
