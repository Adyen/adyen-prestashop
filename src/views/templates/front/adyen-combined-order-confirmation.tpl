{if !empty($adyenAction)}
    <div class="row">
        <input type="hidden" id="adyen-checkout-config-url"
               value="{$checkoutConfigUrl|escape:'html':'UTF-8'}">
        <div id="adyen-additional-action" style="display: none;" type="text/html">{$adyenAction|unescape:'html'}</div>
        <input type="hidden" id="adyen-additional-data-url" value="{$additionalDataUrl|escape:'html':'UTF-8'}">
        <div id="adyen-additional-data"></div>
    </div>
{/if}

{if ($enabled)}
    <div class="card-block">
        <div class="row" style="width: 70%; margin-left: 150px">
            <div id='donation-container'
                 data-donationsConfigUrl="{$donationsConfigUrl}"
                 data-makeDonationsUrl="{$makeDonationsUrl}"
            ></div>
        </div>
    </div>
{/if}
