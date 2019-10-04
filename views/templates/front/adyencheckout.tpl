{if !$originKey}
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
{else}
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            window.adyenCheckout = new AdyenCheckout({
                locale: "{$locale}",
                originKey: "{$originKey}",
                environment: "{$environment}",
                risk: {
                    enabled: false
                }
            });
        });
    </script>

{/if}