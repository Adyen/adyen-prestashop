<input type="hidden" name="adyen-pay-by-link-title"
       value="{html_entity_decode($payByLinkTitle|escape:'html':'UTF-8')}">

<div class="form-group row type-text" id="adyen-expires-at" style="display: none">
    <label for="adyen-expires-at" class="form-control-label">
        <span class="text-danger">*</span>
        {l s='Payment link expires at' mod='adyenofficial'}
    </label>
    <div class="col-sm-2">
            <input type="date" class="form-control"
                   id="adyen-expires-at-date" name="adyen-expires-at-date"
                   value="{html_entity_decode($expirationDate|escape:'html':'UTF-8')}"
                   aria-label="customer_date_add_from input"
                   style="text-align: center">
    </div>
</div>
