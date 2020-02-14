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
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *}

{if !$originKey}
    <h5>There is an error with retrieving the originKey,
        please check your API key in the Adyen Module configuration</h5>
{else}
    <script>
        var IS_PRESTA_SHOP_16 = {$isPrestaShop16};

        var ADYEN_CHECKOUT_CONFIG = {
            locale: "{$locale}",
            originKey: "{$originKey}",
            environment: "{$environment}",
            showPayButton: false,
            paymentMethodsResponse: {$paymentMethodsResponse nofilter}
        };
    </script>
{/if}