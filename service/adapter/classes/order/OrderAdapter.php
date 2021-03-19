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

namespace Adyen\PrestaShop\service\adapter\classes\order;

use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Order;
use OrderSlip;
use PrestaShopDatabaseException;
use PrestaShopException;

class OrderAdapter
{
    /**
     * @var VersionChecker
     */
    protected $versionChecker;

    /**
     * OrderAdapter constructor.
     *
     * @throws \Adyen\AdyenException
     */
    public function __construct()
    {
        $this->versionChecker = ServiceLocator::get('Adyen\PrestaShop\application\VersionChecker');
    }

    /**
     * Returns the order instance for cart id
     *
     * @param $cartId
     * @return null|\Order
     */
    public function getOrderByCartId($cartId)
    {
        $order = null;

        if ($this->versionChecker->isPrestashop16()) {
            $orderId = \Order::getOrderByCartId($cartId);
            if ($orderId) {
                $order = new \Order($orderId);
            }
        } else {
            $order = \Order::getByCartId($cartId);
        }

        return $order;
    }

    /**
     * @param string $orderSlipId
     *
     * @return Order
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrderByOrderSlipId($orderSlipId)
    {
        $orderSlip = new OrderSlip($orderSlipId);
        return new Order($orderSlip->id_order);
    }
}
