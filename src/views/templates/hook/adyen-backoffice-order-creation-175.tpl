<input type="hidden" name="adyen-pay-by-link-title"
       value="{html_entity_decode($payByLinkTitle|escape:'html':'UTF-8')}">

<div class="form-group" id="adyen-expires-at" style="display: none">
    <label for="adyen-expires-at" class="control-label col-lg-3 required">
        <span class="label-tooltip" data-toggle="tooltip" data-html="true">
		 {l s='Payment link expires at' mod='adyenofficial'}
		</span>
    </label>
    <div class="col-lg-4">
        <div class="input-group">
            <input type="date" class="form-control"
                   id="adyen-expires-at-date" name="adyen-expires-at-date"
                   value="{html_entity_decode($expirationDate|escape:'html':'UTF-8')}"
                   aria-label="customer_date_add_from input"
                   style="text-align: center">
        </div>
    </div>
</div>
