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
                    Validate module installation. For more information, check out <a target="_blank" href="https://docs.adyen.com/plugins/prestashop#finding-the-logs">our docs</a>.
                </p>
                <form id="validateForm" action="#" method="POST">
                    <input type="hidden" name="validate" value="1">
                    <button type="submit" class="btn btn-primary-reverse btn-outline-primary">Validate</button>
                </form>
            </div>
        </div>
    </div>
</div>
