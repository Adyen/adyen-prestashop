{if !$originKey}
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
{else}
    <script>
        var IS_PRESTA_SHOP_16 = {$isPrestaShop16};

        var ADYEN_CHECKOUT_CONFIG = {
            locale: "{$locale}",
            originKey: "{$originKey}",
            environment: "{$environment}",
            paymentMethodsResponse: {$paymentMethodsResponse nofilter}
        };
    </script>
{/if}