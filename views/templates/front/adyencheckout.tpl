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

    {*{if $prestashop16}*}
        {*<div style="display:none">*}
            {*<div id="threeDS2Modal">*}
                {*<div id="threeDS2Container"></div>*}
            {*</div>*}
        {*</div>*}

    {*{else}*}
        {*<div id="threeDS2Modal" class="modal fade" tabindex="-1" role="dialog">*}
            {*<div class="modal-dialog" role="document">*}
                {*<div class="modal-content">*}
                    {*<div class="modal-header">*}
                        {*<h4 class="modal-title">Authentication</h4>*}
                    {*</div>*}
                    {*<div class="modal-body">*}
                        {*<div id="threeDS2Container"></div>*}
                    {*</div>*}
                {*</div>*}
            {*</div>*}
        {*</div>*}
    {*{/if}*}
{/if}