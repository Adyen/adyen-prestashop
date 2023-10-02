<div class="tab-pane" id="adyenTabContent">
    <input type="hidden" name="adyen-refund-supported"
           value="{html_entity_decode($refundSupported|escape:'html':'UTF-8')}">
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
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{html_entity_decode($transactionDate|escape:'html':'UTF-8')} </td>
                <td>{html_entity_decode($originalReference|escape:'html':'UTF-8')} </td>
                <td><img src="{html_entity_decode($methodLogo|escape:'html':'UTF-8')} " alt="logo"
                         style="width: 77px;  height: 55px;"> {html_entity_decode($paymentMethod|escape:'html':'UTF-8')}
                </td>
                <td>{html_entity_decode($status|escape:'html':'UTF-8')} </td>
                <td>{html_entity_decode($orderAmount|escape:'html':'UTF-8')} {html_entity_decode($currencyISO|escape:'html':'UTF-8')} </td>
                <td>{html_entity_decode($refundedAmount|escape:'html':'UTF-8')} {html_entity_decode($currencyISO|escape:'html':'UTF-8')}  </td>
            </tr>
            </tbody>
        </table>
    </div>

    <p class="mb-1">
        <strong>{l s='Status' mod='adyenofficial'} </strong>
    </p>
    <div class="row">
        <div class="col-sm-2"><p class="text-muted"><i
                        class="icon-calendar-o"></i> {html_entity_decode($statusDate|escape:'html':'UTF-8')} -</p></div>
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
                <input type="hidden" name="adyen-orderId" value="{html_entity_decode($orderId|escape:'html':'UTF-8')}">
                <button class="btn btn-default" type="button" id="adyen-capture-button">
                    <i class="icon-money"></i>
                    {l s='Capture' mod='adyenofficial'}</button>
                <a class="btn btn-default" type="button" href="{html_entity_decode($adyenLink|escape:'html':'UTF-8')}"
                   target="_blank">
                    <i class="icon-eye"></i>
                    {l s='View payment on Adyen CA' mod='adyenofficial'}</a>
            </div>
        </div>
    {else}
        <div class="input-group">
            <a class="btn btn-default" type="button" href="{html_entity_decode($adyenLink|escape:'html':'UTF-8')}"
               target="_blank">
                <i class="icon-eye"></i>
                {l s='View payment on Adyen CA' mod='adyenofficial'}</a>
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
</div>
