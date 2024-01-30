{if !empty($adyenAction)}
    <div class="row">
        <input type="hidden" id="adyen-checkout-config-url"
               value="{$checkoutConfigUrl|escape:'html':'UTF-8'}">
        <div id="adyen-additional-action" style="display: none;" type="text/html">{$adyenAction|unescape:'html'}</div>
        <input type="hidden" id="adyen-additional-data-url" value="{$additionalDataUrl|escape:'html':'UTF-8'}">
        <div id="adyen-additional-data"></div>
    </div>
{/if}
