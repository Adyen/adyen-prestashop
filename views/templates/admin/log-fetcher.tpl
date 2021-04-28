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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *}
<div class="container">
    <div class="row">
        <div class="col-lg-4 col-lg-offset-4">
            <div class="log-container adyen">
                <img class="img-responsive logo" src="{$logo|escape:'html':'UTF-8'}" alt="logo">
                <p>
                    Download all Adyen-related log files and optionally include other Prestashop logs. For more information, check out <a target="_blank" href="https://docs.adyen.com/plugins/prestashop#downloading-the-logs">our docs</a>.
                </p>
                <form id="downloadForm" action="{$downloadUrl}" method="POST">
                    <div class="checkbox">
                        <label>
                            <input name="include-all" type="checkbox">Include all log files
                        </label>
                    </div>
                    <input type="hidden" name="download" value="1">
                    <button type="submit" class="btn btn-primary-reverse btn-outline-primary">Download</button>
                </form>
            </div>
        </div>
    </div>
</div>
