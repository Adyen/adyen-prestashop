/*
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
 */

jQuery(document).ready(function() {
    const prodSection = $("div:contains('Production Settings'):last").parent();
    const testSection = $("div:contains('Test Settings'):last").parent();

    $("#configuration_form input[name='ADYEN_MODE']").change(function () {
        setRequiredParams(this.value);
    });

    /**
     * Show which params are requried based on mode
     *
     * @param mode
     */
    function setRequiredParams(mode) {
        if (mode === 'live') {
            prodSection.find('.control-label').addClass('required');
            testSection.find('.control-label').removeClass('required');
        } else {
            testSection.find('.control-label').addClass('required');
            prodSection.find('.control-label').removeClass('required');
        }
    }
});
