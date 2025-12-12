{if $adyenShowExpressCheckout }
    <div id="adyen-express-checkout">
        {assign var="adyenExpressCheckoutPaymentTypes" value=['applepay', 'amazonpay', 'paywithgoogle', 'paypal']}
        {foreach $adyenExpressCheckoutPaymentTypes as $adyenPaymentMethodType}
            <div class="adyen-express-checkout-element"
                 id="adyen-express-checkout-{$adyenPaymentMethodType|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-type" name="adyen-type"
                       value="{$adyenPaymentMethodType|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-config-url" value="{$configURL|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-action-url" value="{$paymentActionURL|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-token" value="{$token|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-state-data-url" value="{$stateDataURL|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-get-state-data-url" value="{$getStateDataURL|escape:'html':'UTF-8'}">
                <input type="hidden" name="adyen-additional-data">
                <input type="hidden" class="adyen-redirect-action-url"
                       value="{$paymentRedirectActionURL|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-presta-version" value="{$version|escape:'html':'UTF-8'}">
                <input type="hidden" class="adyen-paypal-update-order-url" value="{$paypalUpdateOrderUrl|escape:'html':'UTF-8'}">
            </div>
        {/foreach}
        <input type="hidden" name="adyenShippingAddress">
        <input type="hidden" name="adyenBillingAddress">
        <input type="hidden" name="adyenEmail">
        {if $customer.is_logged}
            <input type="hidden" name="adyenLoggedIn">
        {/if}
    </div>
{/if}
