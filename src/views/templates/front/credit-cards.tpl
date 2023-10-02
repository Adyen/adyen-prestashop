{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='Stored credit cards (' mod='adyenofficial'} {$numberOfStoredPaymentMethods} {l s=')' d='Shop.Theme.Customeraccount'}
{/block}

{block name='page_content'}
    <div class="stored-credit-cards">
        {if $storedPaymentMethods}
            <input type="hidden" id="adyen-delete-url" value="{$deletionUrl|escape:'html':'UTF-8'}">
            {foreach $storedPaymentMethods as $storedPaymentMethod}
                <div class="col-lg-6 col-md-6 col-sm-6">
                    {block name='stored_card'}
                        <article id="stored_card-{$storedPaymentMethod.id}"
                                 data-id-stored_card="{$storedPaymentMethod.id}">
                            <div class="stored-credit-card-div">
                                <div class="stored-credit-card-info">
                                    <h4>**** **** **** {$storedPaymentMethod.lastFour}</h4>
                                    <label>{$storedPaymentMethod.name}</label><br>
                                    <label>{l s='Expires' mod='adyenofficial'} {$storedPaymentMethod.expiryDate}</label>
                                </div>

                                {block name='stored_card_block_item_actions'}
                                    <div class="text-sm-center stored-credit-card-button">
                                        <button class="btn btn-primary center-block adyen-delete-btn"
                                                data-adyen-method-id="{$storedPaymentMethod.id}">
                                            <i class="material-icons shopping-cart" aria-hidden="true">delete</i>
                                            {l s='Delete' mod='adyenofficial'}
                                        </button>
                                    </div>
                                {/block}
                            </div>
                        </article>
                    {/block}
                </div>
                <div id="adyen-modal-{$storedPaymentMethod.id}" class="modal modal-vcenter adyen-modal"
                     data-backdrop="static"
                     data-keyboard="false" style="display: none;" aria-hidden="true">
                    <div class="modal-dialog adyen-modal-dialog">
                        <div class="modal-content adyen-modal-content">
                            <div class="modal-header adyen-modal-header">
                                <h4 class="modal-title module-modal-title adyen-modal-title">
                                    {l s='Please confirm delete action' mod='adyenofficial'}</h4>
                                <button id="module-modal-import-closing-cross" type="button"
                                        class="close adyen-close-window-{$storedPaymentMethod.id}">×
                                </button>
                            </div>
                            <div class="modal-body">
                                <label> {l s='Are you sure you want to delete this stored credit card?' mod='adyenofficial'} </label>
                                <div class="adyen-modal-error-container adyen-hidden">
                                    <label>{l s='Disable action could not be processed, invalid request.' mod='adyenofficial'} </label>
                                </div>
                            </div>
                            <div class="adyen-modal-footer">
                                <button type="button"
                                        class="adyen-cancel-delete-btn-{$storedPaymentMethod.id} adyen-cancel-button">
                                    {l s='Cancel' mod='adyenofficial'}
                                </button>
                                <button type="button"
                                        class="adyen-confirm-delete-btn-{$storedPaymentMethod.id} adyen-delete-button">
                                    {l s='Delete' mod='adyenofficial'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            {/foreach}
        {else}
            <div class="alert alert-info" role="alert" data-alert="info">
                {l s='No stored credit cards are available.' mod='adyenofficial'}
            </div>
        {/if}
        <div class="clearfix"></div>
    </div>
{/block}