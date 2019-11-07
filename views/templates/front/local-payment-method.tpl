{if !$originKey}
    <form id="payment-form" method="post">
        <h5>There is an error with retrieving the originKey,
            please check your API key in the Adyen Module configuration</h5>
    </form>
{else}
    <div class="row">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <div class="adyen-payment-method-label">{l s='Pay with ' mod='adyen'}{$paymentMethodName}</div>
        <form action="{$paymentProcessUrl}" method="post">
            <div id="adyen-payment-container-{$paymentMethodType}"></div>
            <div id="adyen-payment-errors-{$paymentMethodType}" role="alert"></div>
            <input type="hidden" name="adyen-payment-issuer" id="adyen-payment-issuer-{$paymentMethodType}">
            <input type="hidden" name="adyen-payment-type" id="adyen-payment-type-{$paymentMethodType}"
                   value="{$paymentMethodType}">
            {if $renderPayButton}
                <button type="submit" class="button btn btn-default standard-checkout button-medium">
                    <span>{l s='Pay' mod='adyen'} <i class="icon-chevron-right right"></i> </span>
                </button>
            {/if}
            <script>
                $(document).ready(function () {
                    var issuerInput = document.getElementById('adyen-payment-issuer-{$paymentMethodType}');
                    var configuration = {
                        'onChange': function (state) {
                            if (state.isValid) {
                                issuerInput.value = state.data.paymentMethod.issuer;
                            }
                        }
                    };
                    var issuerList = {$issuerList nofilter};
                    if (issuerList.length) {
                        configuration.items = issuerList;
                    }
                    adyenCheckout.create('{$paymentMethodType}', configuration).mount('#adyen-payment-container-{$paymentMethodType}');
                });
            </script>
        </form>
    </div>
{/if}
