<div class="tab-pane" id="adyenTabContent">
    {if $isAdyenOrder}
        <input type="hidden" name="adyen-presta-version"
               value="1.7.5">
        <div class="table-container table-responsive" style="border: none">
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
                                {html_entity_decode($transactionHistory.originalReference|escape:'html':'UTF-8')}
                            </td>
                            <td>
                                {if $transactionHistory.methodLogo}
                                    <img src="{html_entity_decode($transactionHistory.methodLogo|escape:'html':'UTF-8')} "
                                         alt="logo"
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
                                    <div class="row" style="display:flex">
                                        <div class="col-sm-6" style="padding:0">
                                            {if $transactionHistory.partialCapture}
                                                <div class="input-group">
                                                    <input type="text" name="adyen-capture-amount"
                                                           value="{html_entity_decode($transactionHistory.capturableAmount|escape:'html':'UTF-8')}"
                                                           class="form-control">
                                                    <div class="input-group-addon">
                                                        <span class="input-group-text">{html_entity_decode($transactionHistory.currency|escape:'html':'UTF-8')}</span>
                                                    </div>
                                                </div>
                                            {/if}
                                        </div>
                                        <div class="col-sm-6">
                                            <input type="hidden" name="adyen-psp-reference"
                                                   value="{html_entity_decode($transactionHistory.originalReference|escape:'html':'UTF-8')}">
                                            <input type="hidden" name="adyen-capture-url"
                                                   value="{html_entity_decode($transactionHistory.captureURL|escape:'html':'UTF-8')}">
                                            <input type="hidden" name="adyen-capturable-amount"
                                                   value="{html_entity_decode($transactionHistory.capturableAmount|escape:'html':'UTF-8')}">
                                            <input type="hidden" name="adyen-orderId"
                                                   value="{html_entity_decode($transactionHistory.orderId|escape:'html':'UTF-8')}">
                                            <button class="btn btn-default" type="button" id="adyen-capture-button">
                                                <i class="icon-money"></i>
                                                {l s='Capture' mod='adyenofficial'}</button>
                                        </div>
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
        <div class="row">
            <div class="col-sm-2"><p class="text-muted"><i
                            class="icon-calendar-o"></i> {html_entity_decode($statusDate|escape:'html':'UTF-8')} -</p>
            </div>
            <div class="col-sm-2"><strong> {html_entity_decode($status|escape:'html':'UTF-8')} </strong>
            </div>
        </div>
        <p class="mb-1">
            <strong>{l s='Merchant ID' mod='adyenofficial'} </strong>
        </p>
        <p>{html_entity_decode($merchantID|escape:'html':'UTF-8')} </p>
        <p class="mb-1">
            <strong>{l s='Risk score' mod='adyenofficial'} </strong>
        </p>
        <p>{html_entity_decode($riskScore|escape:'html':'UTF-8')} </p>
        {if $displayAdjustmentButton && $authorizationAdjustmentDate}
            <p class="mb-1">
                <strong>{l s='Authorization adjustment date' mod='adyenofficial'} </strong>
            </p>
            <div class="row">
                <div class="col-sm-2"><p class="text-muted">
                        <i class="icon-calendar-o"></i> {html_entity_decode($authorizationAdjustmentDate|escape:'html':'UTF-8')}
                    </p>
                </div>
            </div>
        {/if}

        {if $displayAdjustmentButton}
            <div class="row">
                <div class="form-group input-group">
                    <input type="hidden" name="adyen-orderId"
                           value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                    <input type="hidden" name="adyen-extend-authorization-url"
                           value="{html_entity_decode($extendAuthorizationURL|escape:'html':'UTF-8')}">
                    <button class="btn btn-default" type="button" id="adyen-extend-authorization-button">
                        <i class="icon-plus-sign"></i>
                        {l s='Extend authorization expiry period' mod='adyenofficial'}</button>
                </div>
            </div>
        {/if}

        {if $captureAvailable}
            <div class="row">
                <div class="col-sm-2">
                    <div class="input-group">
                        <input type="text" name="adyen-capture-amount"
                               value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}"
                               class="form-control">
                        <div class="input-group-addon">
                            <span class="input-group-text">{html_entity_decode($currency|escape:'html':'UTF-8')}</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <input type="hidden" name="adyen-capture-url"
                           value="{html_entity_decode($captureURL|escape:'html':'UTF-8')}">
                    <input type="hidden" name="adyen-capturable-amount"
                           value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
                    <input type="hidden" name="adyen-orderId"
                           value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                    <button class="btn btn-default" type="button" id="adyen-capture-button">
                        <i class="icon-money"></i>
                        {l s='Capture' mod='adyenofficial'}</button>
                    <a class="btn btn-default" type="button"
                       href="{html_entity_decode($adyenLink|escape:'html':'UTF-8')}"
                       target="_blank">
                        <i class="icon-eye"></i>
                        {l s='View payment on Adyen CA' mod='adyenofficial'}</a>
                </div>
            </div>
        {/if}

        {if $shouldDisplayPaymentLink && !$adyenPaymentLink}
            <div class="form-group input-group">
                <input type="hidden" name="adyen-payment-link-amount"
                       value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
                <input type="hidden" name="adyen-orderId"
                       value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                <input type="hidden" name="adyen-generate-payment-link-url"
                       value="{html_entity_decode($adyenGeneratePaymentLink|escape:'html':'UTF-8')}">
                <button class="btn btn-default" type="button" id="adyen-generate-payment-link-button">
                    <i class="icon-link"></i>
                    {l s='Generate a payment link' mod='adyenofficial'}</button>
            </div>
        {/if}

        {if $shouldDisplayPaymentLink && $adyenPaymentLink}
            <div class="form-group">
                <div class="row">
                    <div class="col-lg-6" style="display: flex; align-items: center;">
                        <input type="text"
                               name="adyenPaymentLinkInput"
                               value="{$adyenPaymentLink}"
                               id="adyen-payment-link"
                               disabled>
                        <button name="adyenPaymentLinkButton" class="btn btn-primary" id="adyen-copy-payment-link">
                            {l s='COPY PAYMENT LINK' mod='adyenofficial'}
                        </button>
                    </div>
                </div>
            </div>
        {/if}
        <div class="table-container">
            <h2>{l s='Transaction history' mod='adyenofficial'}</h2>
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
            <div class="form-group input-group">
                <input type="hidden" name="adyen-payment-link-amount"
                       value="{html_entity_decode($capturableAmount|escape:'html':'UTF-8')}">
                <input type="hidden" name="adyen-orderId"
                       value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                <input type="hidden" name="adyen-generate-payment-link-url"
                       value="{html_entity_decode($adyenGeneratePaymentLink|escape:'html':'UTF-8')}">
                <button class="btn btn-default" type="button" id="adyen-generate-payment-link-button">
                    <i class="icon-link"></i>
                    {l s='Generate a payment link' mod='adyenofficial'}</button>
            </div>
        {/if}

        {if $shouldDisplayPaymentLinkForNonAdyenOrder && $adyenPaymentLink}
            <div class="form-group">
                <div class="row">
                    <div class="col-lg-6" style="display: flex; align-items: center;">
                        <input type="text"
                               name="adyenPaymentLinkInput"
                               value="{$adyenPaymentLink}"
                               id="adyen-payment-link"
                               disabled>
                        <button name="adyenPaymentLinkButton" class="btn btn-primary" id="adyen-copy-payment-link">
                            {l s='COPY PAYMENT LINK' mod='adyenofficial'}
                        </button>
                    </div>
                </div>
            </div>
        {/if}
    {/if}
</div>
