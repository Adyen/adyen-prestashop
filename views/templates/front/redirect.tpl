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

{if $redirectMethod == "GET"}
    <body>
    <script>
        window.location.replace("{$issuerUrl}");
    </script>
    </body>
{/if}

<body onload="document.getElementById('3dform').submit();">
<form method="POST" action="{$issuerUrl}" id="3dform">
    <input type="hidden" name="PaReq" value="{$paRequest}"/>
    <input type="hidden" name="MD" value="{$md}"/>
    <input type="hidden" name="TermUrl" value="{$termUrl}"/>
    <noscript>
        <br>
        <br>
        <div style="text-align: center">
            <h1>Processing your 3-D Secure Transaction</h1>
            <p>Please click continue to continue the processing of your 3-D Secure transaction.</p>
            <input type="submit" class="button" value="continue"/>
        </div>
    </noscript>
</form>
</body>
