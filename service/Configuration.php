<?php
/**
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
 */

namespace Adyen\PrestaShop\service;

class Configuration
{
    const CHECKOUT_COMPONENT_JS_TEST = 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/3.4.0/adyen.js';
    const CHECKOUT_COMPONENT_JS_LIVE = 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.4.0/adyen.js';
    const CHECKOUT_COMPONENT_CSS_TEST = 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/3.4.0/adyen.css';
    const CHECKOUT_COMPONENT_CSS_LIVE = 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.4.0/adyen.css';
}
