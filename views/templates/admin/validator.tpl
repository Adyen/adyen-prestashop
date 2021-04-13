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
                <form id="validateForm" action="{$validateUrl}" method="GET">
                    <button type="submit" class="btn btn-primary-reverse btn-outline-primary">Validate</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    jQuery(document).ready(function () {
        $('#validateForm').submit(function (e) {
            e.preventDefault();
            $.get($(e.target).attr('action'), { validate: 1 })
                .done(function(e) {
                    console.log(e);
                    $.growl.notice({ title: "Success", message: "Adyen module successfully validated", duration: 5000});
                })
                .error(function () {
                    $.growl.error({ title: "Error", message: "Please check the logs for more information.", duration: 5000});
                });
        })
    });
</script>
