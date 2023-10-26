<div class="tab-pane d-print-block fade active show" id="adyenTabContent" aria-labelledby="adyenTab">
    {if $isAdyenOrder}
    <input type="hidden" name="adyen-refund-supported"
           value="{html_entity_decode($refundSupported|escape:'html':'UTF-8')}">
    <input type="hidden" name="adyen-presta-version"
           value="1.7.7">
    <div class="table-responsive">
        <h2>{l s='Transaction details' mod='adyenofficial'}</h2>
        <table class="table">
            <thead>
            <tr>
                <th>{l s='Date' mod='adyenofficial'}</th>
                <th>{l s='PSP reference' mod='adyenofficial'}</th>
                <th>{l s='Payment method' mod='adyenofficial'}</th>
                <th>{l s='Status' mod='adyenofficial'}</th>
                <th>{l s='Order amount' mod='adyenofficial'}</th>
                <th>{l s='Refunded amount' mod='adyenofficial'}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{html_entity_decode($transactionDate|escape:'html':'UTF-8')} </td>
                <td>{html_entity_decode($originalReference|escape:'html':'UTF-8')} </td>
                <td>{if $methodLogo}
                        <img src="{html_entity_decode($methodLogo|escape:'html':'UTF-8')} " alt=""
                             style="width: 77px;  height: 55px;">
                        {html_entity_decode($paymentMethod|escape:'html':'UTF-8')}
                    {/if}
                </td>
                <td>{html_entity_decode($status|escape:'html':'UTF-8')} </td>
                <td>{html_entity_decode($orderAmount|escape:'html':'UTF-8')} {html_entity_decode($currencyISO|escape:'html':'UTF-8')}  </td>
                <td>{html_entity_decode($refundedAmount|escape:'html':'UTF-8')} {html_entity_decode($currencyISO|escape:'html':'UTF-8')} </td>
            </tr>
            </tbody>
        </table>
    </div>

    <p class="mb-1">
        <strong>{l s='Status' mod='adyenofficial'} </strong>
    </p>

    <div class="row col-md-6">
        <div><p class="text-muted"><i
                        class="material-icons">date_range</i> {html_entity_decode($statusDate|escape:'html':'UTF-8')}
                -</p></div>
        <div class="col-sm-2"><strong> {html_entity_decode($status|escape:'html':'UTF-8')} </strong></div>

    </div>

    <p class="mb-1">
        <strong>{l s='Merchant ID' mod='adyenofficial'} </strong>
    </p>
    <p>{html_entity_decode($merchantID|escape:'html':'UTF-8')} </p>

    <p class="mb-1">
        <strong>{l s='Risk score' mod='adyenofficial'} </strong>
    </p>
    <p>{html_entity_decode($riskScore|escape:'html':'UTF-8')} </p>

    {if $captureAvailable}
        <div class="row">
            <div class="col-md-12 col-lg-6">
                <div class="form-group card-details-actions">
                    <div class="input-group">
                        <input type="text" name="adyen-capture-amount" class="form-control mt-2"
                               value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
                        <div class="input-group-append mr-2 mt-2">
                            <div class="input-group-text">{html_entity_decode($currency|escape:'html':'UTF-8')}</div>
                        </div>
                        <div>
                            <input type="hidden" name="adyen-capture-url"
                                   value="{html_entity_decode($captureURL|escape:'html':'UTF-8')}">
                            <input type="hidden" name="adyen-capturable-amount"
                                   value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
                            <input type="hidden" name="adyen-orderId"
                                   value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                            <button class="btn btn-outline-secondary mr-2 mt-2" type="button" id="adyen-capture-button">
                                <i class="material-icons">attach_money</i>
                                {l s='Capture' mod='adyenofficial'}</button>

                            <a class="btn btn-outline-secondary mt-2" type="button"
                               href="{html_entity_decode($adyenLink|escape:'html':'UTF-8')}" target="_blank"><i
                                        class="material-icons">remove_red_eye</i>
                                {l s='View payment on Adyen CA' mod='adyenofficial'}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {elseif $methodLogo}
        <div class="form-group">
            <a class="btn btn-outline-secondary" type="button"
               href="{html_entity_decode($adyenLink|escape:'html':'UTF-8')}" target="_blank"><i
                        class="material-icons">remove_red_eye</i>
                {l s='View payment on Adyen CA' mod='adyenofficial'}</a>
        </div>
    {/if}

    {if $shouldDisplayPaymentLink && !$adyenPaymentLink}
        <div class="form-group">
            <input type="hidden" name="adyen-payment-link-amount"
                   value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
            <input type="hidden" name="adyen-orderId"
                   value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
            <input type="hidden" name="adyen-generate-payment-link-url"
                   value="{html_entity_decode($adyenGeneratePaymentLink|escape:'html':'UTF-8')}">
            <button class="btn btn-primary" type="button" id="adyen-generate-payment-link-button"><i
                        class="material-icons">link</i>
                {l s='Generate a payment link' mod='adyenofficial'}</button>
        </div>
    {/if}

    {if $shouldDisplayPaymentLink && $adyenPaymentLink}
        <div class="form-group input-group">
            <input type="text"
                   name="adyenPaymentLinkInput"
                   class="col-md-6 form-control"
                   value="{$adyenPaymentLink}"
                   id="adyen-payment-link"
                   disabled>
            <div class="input-group-append">
                <button name="adyenPaymentLinkButton" class="btn btn-sm btn-primary" id="adyen-copy-payment-link">
                    {l s='COPY PAYMENT LINK' mod='adyenofficial'}
                </button>
            </div>
        </div>
    {/if}

    <div class="table-responsive">
        <h3>{l s='Transaction history' mod='adyenofficial'}</h3>
        <table class="table">
            <thead>
            <tr>
                <th>{l s='Event code' mod='adyenofficial'}</th>
                <th>{l s='Date' mod='adyenofficial'}</th>
                <th>{l s='Status' mod='adyenofficial'}</th>
            </tr>
            </thead>
            <tbody>
            {foreach $transactionHistory as $item}
                <tr>
                    <td>{$item.eventCode}</td>
                    <td>{$item.date}</td>
                    <td>{if $item.status} true {else} false {/if}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
    {else}
        {if $shouldDisplayPaymentLink}
                <input type="hidden" name="adyen-payment-link-amount"
                       value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
                <input type="hidden" name="adyen-orderId"
                       value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                <input type="hidden" name="adyen-generate-payment-link-url"
                       value="{html_entity_decode($adyenGeneratePaymentLink|escape:'html':'UTF-8')}">
                <button class="btn btn-primary" type="button" id="adyen-generate-payment-link-button"><i
                            class="material-icons">link</i>
                    {l s='Generate a payment link' mod='adyenofficial'}</button>
        {/if}
    {/if}
</div>
