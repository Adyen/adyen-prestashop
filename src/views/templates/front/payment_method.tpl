<div class="adyen-payment-method">
    <p>{$description|escape:'htmlall':'UTF-8'}</p>
    <form class="adyen-form-{$paymentMethodType|escape:'html':'UTF-8'}"
          action="{$paymentActionURL|escape:'html':'UTF-8'}" method="POST"
            {if $paymentMethodType==='applepay'} onsubmit="preventSubmit();"{/if}>
        <input type="hidden" name="adyen-type" value="{$paymentMethodType|escape:'html':'UTF-8'}">
        <input type="hidden" name="adyen-payment-method-id" value="{$paymentMethodId|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-config-url" value="{$configURL|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-action-url" value="{$paymentActionURL|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-token" value="{$token|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-state-data-url" value="{$stateDataURL|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-get-state-data-url" value="{$getStateDataURL|escape:'html':'UTF-8'}">
        <input type="hidden" name="adyen-additional-data">
        <input type="hidden" name="adyen-giftcards-data">
        <input type="hidden" name="adyen-stored-value" value="{$stored|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-redirect-action-url"
               value="{$paymentRedirectActionURL|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-checkout-url" value="{$checkoutUrl|escape:'html':'UTF-8'}">
        <input type="hidden" class="adyen-balance-check-url" value="{$balanceCheckUrl|escape:'html':'UTF-8'}">
        <input type="hidden" name="adyen-presta-version" value="{$prestaVersion|escape:'html':'UTF-8'}">
        <p id="adyen-click-to-pay-label" class="adyen-click-to-pay-label"> {$clickToPayLabel|escape:'html':'UTF-8'} </p>
        <input type="hidden" name="adyen-giftcard-remove" value="{l s='Remove' mod='adyenofficial'}">
        <input type="hidden" name="adyen-giftcard-complete-order"
               value="{l s='You have paid complete order amount. Please confirm by placing the order.' mod='adyenofficial'}">
        <input type="hidden" name="adyen-giftcard-discount" value="{l s='Giftcard Discount' mod='adyenofficial'}">
        <input type="hidden" name="adyen-giftcard-remaining-amount" value="{l s='Remaining Amount' mod='adyenofficial'}">
        <input type="hidden" name="adyen-giftcard-deducted-amount"
               value="{l s='Deducted amount: {currencySymbol}{cardValue} {currencyIso}' mod='adyenofficial'}">
        <input type="hidden" class="adyen-order-total-amount" value="{$orderTotalAmount|escape:'html':'UTF-8'}">
    </form>
</div>