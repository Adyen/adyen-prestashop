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

namespace Adyen\PrestaShop\service\builder;

use Adyen\PrestaShop\controllers\FrontController;

class Browser
{
    /**
     * Add additional browser info into the request
     *
     * @param string $userAgent
     * @param string $acceptHeader
     * @param array $request
     * @return array
     */
    public function buildBrowserData(
        $userAgent = '',
        $acceptHeader = '',
        $request = array()
    ) {
        if (!empty($userAgent)) {
            $request[FrontController::BROWSER_INFO][FrontController::USER_AGENT] = $userAgent;
        }

        if (!empty($acceptHeader)) {
            $request[FrontController::BROWSER_INFO][FrontController::ACCEPT_HEADER] = $acceptHeader;
        }

        return $request;
    }
}
