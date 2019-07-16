{extends file='page.tpl'}

{block name='page_content_container'}
    <h3>{l s='There was an error' mod='adyen'}</h3>

    <p class="warning">
        {l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='adyen'}
        <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department' mod='adyen'}</a>.
    </p>
    {if isset($error)}
        <p class="warning">
            {$error}
        </p>
    {/if}
    {if isset($return)}
        <p class="cart_navigation clearfix" id="cart_navigation">
            <a
                    class="btn btn-primary"
                    href="{$return}">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='adyen'}
            </a>
        </p>
    {/if}

{/block}

