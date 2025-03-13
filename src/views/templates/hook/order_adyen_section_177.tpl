<div class="tab-pane d-print-block fade active show" id="adyenTabContent" aria-labelledby="adyenTab">
    {if $isAdyenOrder}
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
                    <th>{l s='Initial authorization amount' mod='adyenofficial'}</th>
                    <th>{l s='Capture' mod='adyenofficial'}</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$history item=transactionHistory}
                    {if $transactionHistory.originalReference}
                        <tr>
                            <td>
                                {html_entity_decode($transactionHistory.transactionDate|escape:'html':'UTF-8')}
                            </td>
                            <td>
                                <a href="{html_entity_decode($transactionHistory.adyenLink|escape:'html':'UTF-8')}" target="_blank">
                                    {html_entity_decode($transactionHistory.originalReference|escape:'html':'UTF-8')}
                                </a>
                            </td>
                            <td>
                                {if $transactionHistory.methodLogo}
                                    <img src="{html_entity_decode($transactionHistory.methodLogo|escape:'html':'UTF-8')} " alt=""
                                         style="width: 77px;  height: 55px;">
                                    {html_entity_decode($transactionHistory.paymentMethod|escape:'html':'UTF-8')}
                                {/if}
                            </td>
                            <td>{html_entity_decode($transactionHistory.status|escape:'html':'UTF-8')} </td>
                            <td>
                                {html_entity_decode($transactionHistory.orderAmount|escape:'html':'UTF-8')}
                                {html_entity_decode($transactionHistory.currencyISO|escape:'html':'UTF-8')}
                            </td>
                            <td>
                                <input type="hidden" name="adyen-refund-supported"
                                       value="{html_entity_decode($transactionHistory.refundSupported|escape:'html':'UTF-8')}">
                                {html_entity_decode($transactionHistory.refundedAmount|escape:'html':'UTF-8')}
                                {html_entity_decode($transactionHistory.currencyISO|escape:'html':'UTF-8')}
                            </td>
                            <td>
                                {html_entity_decode($transactionHistory.authorizationAdjustmentAmount|escape:'html':'UTF-8')}
                                {html_entity_decode($transactionHistory.currencyISO|escape:'html':'UTF-8')}
                            </td>
                            {if $transactionHistory.captureAvailable}
                                <td>
                                    <div style="display:flex">
                                        {if $transactionHistory.partialCapture}
                                            <input type="text" name="adyen-capture-amount" class="form-control mt-2"
                                                   value="{html_entity_decode($transactionHistory.capturableAmount|escape:'html':'UTF-8')}">
                                            <div class="input-group-append mr-2 mt-2">
                                                <div class="input-group-text">{html_entity_decode($transactionHistory.currency|escape:'html':'UTF-8')}</div>
                                            </div>
                                        {/if}
                                        <input type="hidden" name="adyen-psp-reference"
                                               value="{html_entity_decode($transactionHistory.originalReference|escape:'html':'UTF-8')}">
                                        <input type="hidden" name="adyen-capture-url"
                                               value="{html_entity_decode($transactionHistory.captureURL|escape:'html':'UTF-8')}">
                                        <input type="hidden" name="adyen-capturable-amount"
                                               value="{html_entity_decode($transactionHistory.capturableAmount|escape:'html':'UTF-8')}">
                                        <input type="hidden" name="adyen-orderId"
                                               value="{html_entity_decode($transactionHistory.orderId|escape:'html':'UTF-8')}">
                                        <button class="btn btn-outline-secondary mr-2 mt-2" type="button"
                                                name="adyen-capture-button">
                                            {l s='Capture' mod='adyenofficial'}</button>
                                    </div>
                                </td>
                            {else}
                                <td>-</td>
                            {/if}
                        </tr>
                    {/if}
                {/foreach}
                </tbody>
            </table>
        </div>
        <p class="mb-1">
            <strong>{l s='Status' mod='adyenofficial'} </strong>
        </p>
        <div class="row col-md-6">
            <div><p class="text-muted">
                    <i class="material-icons">date_range</i>
                    {html_entity_decode($statusDate|escape:'html':'UTF-8')}
                    -</p>
            </div>
            <div class="col-sm-2"><strong> {html_entity_decode($status|escape:'html':'UTF-8')} </strong>
            </div>
        </div>
        <p class="mb-1">
            <strong>{l s='Merchant ID' mod='adyenofficial'} </strong>
        </p>
        <p>{html_entity_decode($merchantID|escape:'html':'UTF-8')} </p>

        {if $displayAdjustmentButton && $authorizationAdjustmentDate}
            <p class="mb-1">
                <strong>{l s='Authorization adjustment date' mod='adyenofficial'} </strong>
            </p>
            <div class="row col-md-6">
                <div><p class="text-muted">
                        <i class="material-icons">date_range</i>
                        {html_entity_decode($authorizationAdjustmentDate|escape:'html':'UTF-8')}
                    </p>
                </div>
            </div>
        {/if}

        {if $displayAdjustmentButton}
            <div class="row">
                <div class="col-md-12 col-lg-6">
                    <div class="form-group card-details-actions">
                        <div class="input-group">
                            <div>
                                <input type="hidden" name="adyen-orderId"
                                       value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                                <input type="hidden" name="adyen-extend-authorization-url"
                                       value="{html_entity_decode($extendAuthorizationURL|escape:'html':'UTF-8')}">
                                <button class="btn btn-outline-secondary mr-2 mt-2" type="button"
                                        id="adyen-extend-authorization-button">
                                    <i class="material-icons">add_circle</i>
                                    {l s='Extend authorization expiry period' mod='adyenofficial'}</button>
                            </div>
                        </div>
                    </div>
                </div>
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
                {foreach from=$history item=transactionHistories}
                    {foreach from=$transactionHistories.transactionHistory item=item}
                        <tr>
                            <td>{$item.eventCode}</td>
                            <td>{$item.date}</td>
                            <td>{if $item.status} true {else} false {/if}</td>
                        </tr>
                    {/foreach}
                {/foreach}
                </tbody>
            </table>
        </div>
    {else}
        {if $shouldDisplayPaymentLinkForNonAdyenOrder && !$adyenPaymentLink}
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

        {if $shouldDisplayPaymentLinkForNonAdyenOrder && $adyenPaymentLink}
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
    {/if}
</div>
