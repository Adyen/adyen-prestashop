<?php

namespace AdyenPayment\Classes\Utility;

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;

/**
 * Class Url
 */
class Url
{
    /**
     * Gets the URL of the admin controller and its action.
     *
     * @param string $controller
     * @param string $action
     * @param string|null $storeId
     * @param string|null $methodId
     * @param string|null $queueItemId
     * @param bool $ajax
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    public static function getAdminUrl(
        string $controller,
        string $action,
        ?string $storeId = null,
        ?string $methodId = null,
        ?string $queueItemId = null,
        bool $ajax = true
    ): string {
        $url = \Context::getContext()->link->getAdminLink($controller) . '&';
        $params = [
            'ajax' => $ajax,
            'action' => $action,
        ];

        $queryString = http_build_query($params);

        self::addQueryParam($queryString, 'storeId', $storeId);
        self::addQueryParam($queryString, 'methodId', $methodId);
        self::addQueryParam($queryString, 'queueItemId', $queueItemId);

        return $url . $queryString;
    }

    /**
     * Gets the URL of the frontend controller.
     *
     * @param string $controller
     * @param array $params
     *
     * @return string
     */
    public static function getFrontUrl(string $controller, array $params = []): string
    {
        $shopId = StoreContext::getInstance()->getStoreId();

        return \Context::getContext()->link->getModuleLink(
            'adyenofficial',
            $controller,
            $params,
            null,
            null,
            $shopId ?: \Context::getContext()->shop->id
        );
    }

    /**
     * Adds query parameter if its value is different from null.
     *
     * @param string $queryString
     * @param string $queryParamName
     * @param string|null $queryParamValue
     *
     * @return void
     */
    private static function addQueryParam(string &$queryString, string $queryParamName, ?string $queryParamValue): void
    {
        if ($queryParamValue !== null) {
            $queryString .= '&' . $queryParamName . '=' . $queryParamValue;
        }
    }
}
