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

{include './adyencheckout.tpl'}

{if !empty($action) or !empty($additionalData)}
    <div class="row">
      <div class="col-md-12">
        <h3 class="card-title h3">{l s='Please use these details to finish the payment' mod='adyenofficial'}:</h3>
        {if !empty($action)}
            <div
                    data-adyen-payment-action-container
                    data-adyen-payment-action="{$action|escape:'html':'UTF-8'}"
            ></div>
        {/if}

          {if !empty($additionalData)}
              <div
                      data-adyen-payment-additional-data-container
                      data-adyen-payment-additional-data="{$additionalData|escape:'html':'UTF-8'}"
              ></div>
          {/if}
        <div data-adyen-payment-error-container role="alert"></div>
      </div>
    </div>
{/if}
