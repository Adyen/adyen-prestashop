{*
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *}

<div style="display: flex;">
    <div style="float:left;padding-right:10px;">
        <img width="120px" src="{$logo|escape:'html':'UTF-8'}">
    </div>
    <div>
        <p>{l s='Adyen all-in-one payments platform.' mod='adyenofficial'}</p>
        <p>{l s='Offer key payment methods anywhere in the world at the flick of a switch.' mod='adyenofficial'}</p>
        <p>{l s='Easy integration with our in-house built PrestaShop Plugin, no set-up fee.' mod='adyenofficial'}</p>
        <p>{l s='Sign up for an Adyen account at' mod='adyenofficial'} <a href="https://www.adyen.com/signup">https://www.adyen.com/signup</a>
        </p>
    </div>
</div>
<div>
    {foreach from=$links item=value name=links}
        <a href="{$value.url|escape:'html':'UTF-8'}" target="_blank">{$value.label|escape:'html':'UTF-8'}</a>
        {if not $smarty.foreach.links.last} | {/if}
    {/foreach}
</div>
