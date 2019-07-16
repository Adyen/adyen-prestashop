{if !$originKey}
    <form id="payment-form" method="post">
        <h5>There is an error with retrieving the originKey,
            please check your API key in the Adyen Module configuration</h5>
    </form>
{else}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <form action="{$action}" id="payment-form" method="post">
                <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
                <script>
                    $(document).ready(function () {
                        renderSecureFields();
                    });

                    function renderSecureFields() {
                        var locale = "en_US";
                        var cardNode = document.getElementById('cardContainer');
                        // we can now rely on $ within the safety of our "bodyguard" function
                        var checkout = new AdyenCheckout({
                            locale: locale,
                            originKey: "{$originKey}",
                            environment: "{$environment}",
                            risk: {
                                enabled: false
                            }
                        });
                        var card = checkout.create('card', {
                            type: 'card',
                            hasHolderName: true,
                            holderNameRequired: true,

                            onChange: function (state, component) {
                                if (state.isValid && !component.state.errors.encryptedSecurityCode) {
                                    document.getElementById('holderName').value = state.data.paymentMethod.holderName;
                                    document.getElementById('encryptedCardNumber').value = state.data.paymentMethod.encryptedCardNumber;
                                    document.getElementById('encryptedExpiryMonth').value = state.data.paymentMethod.encryptedExpiryMonth;
                                    document.getElementById('encryptedExpiryYear').value = state.data.paymentMethod.encryptedExpiryYear;
                                    if (state.data.paymentMethod.encryptedSecurityCode) {
                                        document.getElementById('encryptedSecurityCode').value = state.data.paymentMethod.encryptedSecurityCode;
                                    }
                                    document.getElementById("allValidcard").value = true;
                                } else {
                                    resetFields();
                                }
                            }
                        });
                        card.mount(cardNode);
                    }

                    function resetFields() {
                        document.getElementById('holderName').value = "";
                        document.getElementById('encryptedCardNumber').value = "";
                        document.getElementById('encryptedExpiryMonth').value = "";
                        document.getElementById('encryptedExpiryYear').value = "";
                        document.getElementById('encryptedSecurityCode').value = "";
                        document.getElementById("allValidcard").value = "";
                    }
                </script>
                <div class="checkout-container" id="cardContainer">
                    <div class="form-div">
                        <input type="hidden" name="holderName" id="holderName"
                               value="">
                        <input type="hidden" name="encryptedCardNumber" id="encryptedCardNumber"
                               value="">
                        <input type="hidden" name="encryptedExpiryMonth" id="encryptedExpiryMonth"
                               value="">
                        <input type="hidden" name="encryptedExpiryYear" id="encryptedExpiryYear"
                               value="">
                        <input type="hidden" name="encryptedSecurityCode" id="encryptedSecurityCode"
                               value="">
                        <input type="hidden" class="required-entry" name="allValidcard" id="allValidcard" value="">
                        <input type="hidden" name="payment[screen_width]" id="screenWidth" value="">
                        <input type="hidden" name="payment[screen_height]" id="screenHeight" value="">
                        <input type="hidden" name="payment[color_depth]" id="colorDepth" value="">
                        <input type="hidden" name="payment[time_zone_offset]" id="timeZoneOffset" value="">
                        <input type="hidden" name="payment[language]" id="language" value="">
                        <input type="hidden" name="payment[java_enabled]" id="javaEnabled" value="">
                    </div>
                </div>
                {if $prestashop16}
                    <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                 {l s='Pay' mod='adyen'} <i class="icon-chevron-right right"></i> </span></button>
                {/if}
            </form>
        </div>
    </div>
{/if}