<?php

namespace AdyenPayment\Classes\Utility;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

/**
 * Resolves request data of the current back-office page in a way that works for both legacy admin controllers
 * and Symfony-routed admin pages.
 *
 * Up to PrestaShop 8.x the Symfony layout was rendered through AdminLegacyLayoutController, which populated
 * $_GET['controller'] with the legacy controller name. PrestaShop 9 replaced it with LegacyControllerContext and
 * no longer sets that parameter, so Tools::getValue('controller') is empty on every Symfony admin page.
 */
class AdminRequest
{
    /**
     * Returns the legacy name of the current admin controller (e.g. "AdminOrders").
     *
     * @return string
     */
    public static function getControllerName(): string
    {
        $controller = \Tools::getValue('controller');

        if (!empty($controller)) {
            return (string) $controller;
        }

        $contextController = \Context::getContext()->controller ?? null;

        if ($contextController && !empty($contextController->controller_name)) {
            return (string) $contextController->controller_name;
        }

        return '';
    }

    /**
     * Returns the order ID of the current admin page, taken either from the legacy "id_order" parameter or from the
     * "orderId" attribute of the matched Symfony route.
     *
     * @return int
     */
    public static function getOrderId(): int
    {
        $orderId = (int) \Tools::getValue('id_order');

        if ($orderId) {
            return $orderId;
        }

        $request = self::getSymfonyRequest();

        return $request ? (int) $request->attributes->get('orderId') : 0;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request|null
     */
    private static function getSymfonyRequest()
    {
        if (!class_exists(SymfonyContainer::class) || !SymfonyContainer::getInstance()) {
            return null;
        }

        try {
            $requestStack = SymfonyContainer::getInstance()->get('request_stack');

            return $requestStack ? $requestStack->getCurrentRequest() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
