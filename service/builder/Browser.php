<?php

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
     *
     * @return array
     */
    public function buildBrowserData(
        $userAgent = '',
        $acceptHeader = '',
        $request = []
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
