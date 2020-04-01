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
        <img width="120px" src="{$logo}">
    </div>
    <div>
        <p>Adyen all-in-one payments platform.</p>
        <p>Offer key payment methods anywhere in the world at the flick of a switch.</p>
        <p>Easy integration with our in-house built Prestashop Plugin, no set-up fee.</p>
    </div>
</div>
<div>
    {foreach from=$links item=value name=links}
        <a href="{$value.url}" target="_blank">{$value.label}</a> {if not $smarty.foreach.links.last} | {/if}
    {/foreach}
</div>