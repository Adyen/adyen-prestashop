{if !$originKey}
<form id="payment-form" method="post">
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
</form>
{else}
    <div class="row adyen-payment"
         data-one-click-payment="{$oneClickPaymentMethod|escape:'html'}"
         data-three-ds-process-url="{$threeDSProcessUrl}"
         data-recurring-detail-reference="{$recurringDetailReference}"
    >
    <div class="col-xs-12 col-md-6">
        <form id="payment-form" action="{$paymentProcessUrl}" class="adyen-payment-form-{$recurringDetailReference}" method="post">

            <!-- Display payment errors -->
            <div id="errors" role="alert"></div>

            {if $prestashop16}
                <p></p>
                <div class="adyen-payment-method-label">
                    {l s='Pay with saved ' mod='adyen'} {$name}
                    {l s=' ending: ' mod='adyen'} {$number}
                </div>
            {/if}


            <div class="checkout-container" id="cardContainer-recurringDetailReference"></div>
            <input type="hidden" name="paymentData"/>
            <input type="hidden" name="redirectMethod"/>
            <input type="hidden" name="issuerUrl"/>
            <input type="hidden" name="paRequest"/>
            <input type="hidden" name="md"/>

            {if $prestashop16}
                <div style="display:none">
                    <div id="threeDS2Modal-recurringDetailReference">
                        <div id="threeDS2Container-recurringDetailReference"></div>
                    </div>
                </div>
            {else}
                <div id="threeDS2Modal-recurringDetailReference" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title">Authentication</h4>
                            </div>
                            <div class="modal-body">
                                <div id="threeDS2Container-recurringDetailReference"></div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
            <script>
                document.getElementById("cardContainer-recurringDetailReference").setAttribute("id", "cardContainer-".concat("{$recurringDetailReference}"));
                document.getElementById("threeDS2Modal-recurringDetailReference").setAttribute("id", "threeDS2Modal-".concat("{$recurringDetailReference}"));
                document.getElementById("threeDS2Container-recurringDetailReference").setAttribute("id", "threeDS2Container-".concat("{$recurringDetailReference}"));
            </script>

            {if $prestashop16}
                <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                             {l s='Pay' mod='adyen'} <i class="icon-chevron-right right"></i> </span></button>
            {/if}
        </form>
    </div>
    </div>
{/if}