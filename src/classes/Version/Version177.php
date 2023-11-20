<?php

namespace AdyenPayment\Classes\Version;

use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Context;
use Db;
use Order;
use OrderDetail;
use OrderSlip;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopDatabaseException;
use PrestaShopException;

/**
 * Class Version177. Used from PrestaShop 1.7.7.x+.
 *
 * @package AdyenPayment\Classes\Version
 */
class Version177 implements VersionHandler
{
    /**
     * @inheritDoc
     */
    public function hooks(): array
    {
        $hooks = [
            'displayAdminOrderTabContent',
            'displayAdminOrderTabLink',
            'actionOrderGridDefinitionModifier',
            'actionOrderGridPresenterModifier',
            'displayProductActions'
        ];

        if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
            $hooks[] = 'displayPaymentReturn';
        } else {
            $hooks[] = 'paymentReturn';
        }

        return $hooks;
    }

    /**
     * @inheritDoc
     */
    public function tabLink(): string
    {
        return 'tab_link_177.tpl';
    }

    /**
     * @inheritDoc
     */
    public function tabContent(): string
    {
        return 'order_adyen_section_177.tpl';
    }

    /**
     * @param Order $order
     *
     * @return float
     */
    public function getRefundedAmount(Order $order): float
    {
        /** @var OrderSlip $lastOrderSlip */
        $lastOrderSlip = $order->getOrderSlipsCollection()->getLast();

        return $lastOrderSlip->total_products_tax_incl + $lastOrderSlip->shipping_cost_amount;
    }

    /**
     * @param Order $order
     * @param array $quantityList
     * @return void
     *
     * @throws PrestaShopException
     */
    public function rollbackOrderSlipAdd(Order $order, array $quantityList = []): void
    {
        /** @var OrderSlip $lastOrderSlip */
        $lastOrderSlip = $order->getOrderSlipsCollection()->getLast();
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'order_slip_detail` WHERE `id_order_slip` = ' . (int)$lastOrderSlip->id
        );

        $lastOrderSlip->delete();
    }

    /**
     * @param Order $order
     *
     * @return float
     */
    public function getRefundedAmountOnPresta(Order $order): float
    {
        return $this->getRefundedProducts($order) + $this->getRefundedShipping($order);
    }

    /**
     * @param OrderDetail $orderDetail
     *
     * @param float $amount
     * @param float $amountWithoutTax
     * @param int $quantityRefunded
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function updateOrderDetail(
        OrderDetail $orderDetail,
        float $amount,
        float $amountWithoutTax,
        int $quantityRefunded
    ): void {
        $orderDetail->total_refunded_tax_incl += $amount;
        $orderDetail->total_refunded_tax_excl += $amountWithoutTax;
        $orderDetail->product_quantity_return += $quantityRefunded;
        $orderDetail->product_quantity_reinjected += $quantityRefunded;

        $orderDetail->total_refunded_tax_incl = round($orderDetail->total_refunded_tax_incl, 2);
        $orderDetail->total_refunded_tax_excl = round($orderDetail->total_refunded_tax_excl, 2);

        $orderDetail->update();
    }

    /**
     * @param OrderDetail $orderDetail
     *
     * @return float
     */
    public function getRefundedAmountForOrderDetail(OrderDetail $orderDetail): float
    {
        return $orderDetail->total_refunded_tax_incl;
    }

    /**
     * @param OrderDetail $orderDetail
     * @param int $quantityRefunded
     *
     * @return int
     */
    public function calculateQuantityToAdd(OrderDetail $orderDetail, int $quantityRefunded): int
    {
        return $quantityRefunded + (int)$orderDetail->product_quantity_return + (int)$orderDetail->product_quantity_refunded <= (int)$orderDetail->product_quantity ? $quantityRefunded : (int)$orderDetail->product_quantity - (int)$orderDetail->product_quantity_return - (int)$orderDetail->product_quantity_refunded;
    }

    /**
     * @param OrderDetail $orderDetail
     *
     * @param array $details
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function rollbackOrderDetail(OrderDetail $orderDetail, array $details): void
    {
        $orderDetail->product_quantity_return -= (int)$details['product_quantity'];
        $orderDetail->product_quantity_reinjected -= (int)$details['product_quantity'];
        $orderDetail->total_refunded_tax_incl -= (float)$details['amount_tax_incl'];
        $orderDetail->total_refunded_tax_excl -= (float)$details['amount_tax_excl'];

        $orderDetail->update();
    }

    /**
     * @param string $merchantReference
     *
     * @return string
     */
    public function getOrderUrl(string $merchantReference): string
    {
        $id = Order::getIdByCartId((int)$merchantReference);

        if (!SymfonyContainer::getInstance()) {
            return '';
        }

        return rtrim(Context::getContext()->link->getBaseLink(), '/') . SymfonyContainer::getInstance()->get('router')
                ->generate('admin_orders_view', ['orderId' => $id]);
    }

    /**
     * Gets refunded shipping amount.
     *
     * @param Order $order
     *
     * @return float
     */
    private function getRefundedShipping(Order $order): float
    {
        $amount = 0;

        foreach ($order->getOrderSlipsCollection()->getResults() as $item) {
            $amount += $item->shipping_cost_amount;
        }

        return $amount;
    }

    /**
     * Gets refunded products amount.
     *
     * @param Order $order
     *
     * @return float
     */
    private function getRefundedProducts(Order $order): float
    {
        $amount = 0;

        foreach ($order->getOrderSlipsCollection()->getResults() as $item) {
            $amount += $item->total_products_tax_incl;
        }

        return $amount;
    }
}
