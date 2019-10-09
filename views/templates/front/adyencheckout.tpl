{if !$originKey}
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
{else}
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script>
        {if $prestashop16}
            var paymentMethodsResponse = {$paymentMethodsResponse};
        {else}
            var paymentMethodsResponse = "{$paymentMethodsResponse}".replace(/&quot;/g, '"');
        {/if}
        $(document).ready(function () {
            window.adyenCheckout = new AdyenCheckout({
                locale: "{$locale}",
                originKey: "{$originKey}",
                environment: "{$environment}",
                paymentMethodsResponse: paymentMethodsResponse
            });
        });
    </script>

{/if}