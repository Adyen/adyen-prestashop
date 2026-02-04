<?php

namespace AdyenPayment\Classes\Version;

use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Order;

/**
 * Class Version175. Used from PrestaShop version 1.7.5.0 to 1.7.7.
 */
class Version175 implements VersionHandler
{
    /**
     * {@inheritDoc}
     */
    public function hooks(): array
    {
        return [
            'displayAdminOrderTabShip',
            'displayAdminOrderContentShip',
            'displayProductAdditionalInfo',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function tabLink(): string
    {
        return 'tab_link_175.tpl';
    }

    /**
     * {@inheritDoc}
     */
    public function tabContent(): string
    {
        return 'order_adyen_section_175.tpl';
    }

    /**
     * @param \Order $order
     *
     * @return float
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getRefundedAmount(\Order $order): float
    {
        $orderSlipCollection = $order->getOrderSlipsCollection();

        /** @var \OrderSlip $lastOrderSlip */
        $lastOrderSlip = $orderSlipCollection[count($orderSlipCollection) - 1];
        $productsAmount = $lastOrderSlip->total_products_tax_incl;
        $shippingAmount = $this->calculateShippingAmount($order, $orderSlipCollection, $lastOrderSlip);

        return $productsAmount + $shippingAmount;
    }

    /**
     * @param \Order $order
     * @param array $quantityList
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    public function rollbackOrderSlipAdd(\Order $order, array $quantityList = []): void
    {
        $this->deleteOrderSlip($order);
        $this->revertOrderDetails($order, $quantityList);
    }

    /**
     * @param \Order $order
     *
     * @return float
     */
    public function getRefundedAmountOnPresta(\Order $order): float
    {
        $amount = 0;

        foreach ($order->getOrderSlipsCollection()->getResults() as $item) {
            $amount += $item->shipping_cost_amount + $item->total_products_tax_incl;
        }

        return $amount;
    }

    /**
     * @param \OrderDetail $orderDetail
     * @param float $amount
     * @param float $amountWithoutTax
     * @param int $quantityRefunded
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function updateOrderDetail(
        \OrderDetail $orderDetail,
        float $amount,
        float $amountWithoutTax,
        int $quantityRefunded,
    ): void {
        $orderDetail->product_quantity_refunded += $quantityRefunded;

        $orderDetail->update();
    }

    /**
     * @param \OrderDetail $orderDetail
     *
     * @return float
     */
    public function getRefundedAmountForOrderDetail(\OrderDetail $orderDetail): float
    {
        $db = \Db::getInstance();
        $query = new \DbQuery();
        $query->select('SUM(amount_tax_incl) as refunded_amount')
            ->from('order_slip_detail')
            ->where('id_order_detail = ' . (int) $orderDetail->id);

        $result = $db->getRow($query);

        $refundAmount = 0;
        if ($result && isset($result['refunded_amount'])) {
            $refundAmount = (float) $result['refunded_amount'];
        }

        return $refundAmount;
    }

    /**
     * @param \OrderDetail $orderDetail
     * @param int $quantityRefunded
     *
     * @return int
     */
    public function calculateQuantityToAdd(\OrderDetail $orderDetail, int $quantityRefunded): int
    {
        return $quantityRefunded + (int) $orderDetail->product_quantity_refunded + (int) $orderDetail->product_quantity_return <=
        (int) $orderDetail->product_quantity ?
            $quantityRefunded :
            (int) $orderDetail->product_quantity - (int) $orderDetail->product_quantity_refunded - (int) $orderDetail->product_quantity_return;
    }

    /**
     * @param \OrderDetail $orderDetail
     * @param array $details
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function rollbackOrderDetail(\OrderDetail $orderDetail, array $details): void
    {
        $quantityToRollback = (int) $details['product_quantity'];

        if ((int) $orderDetail->product_quantity_refunded > 0 && (int) $orderDetail->product_quantity_refunded <= $quantityToRollback) {
            $quantityToRollback -= $orderDetail->product_quantity_refunded;
            $orderDetail->product_quantity_refunded = 0;
        }

        if ((int) $orderDetail->product_quantity_refunded > 0 && (int) $orderDetail->product_quantity_refunded > $quantityToRollback) {
            $orderDetail->product_quantity_refunded -= $quantityToRollback;
            $quantityToRollback = 0;
        }

        if ((int) $orderDetail->product_quantity_return > 0 && $quantityToRollback > 0) {
            $orderDetail->product_quantity_return -= $quantityToRollback;
        }

        $orderDetail->update();
    }

    /**
     * @param string $merchantReference
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    public function getOrderUrl(string $merchantReference): string
    {
        $id = \Order::getIdByCartId((int) $merchantReference);

        return \Context::getContext()->link->
            getAdminLink('AdminOrders', false) . '&id_order=' . $id . '&token=' . \Tools::getAdminTokenLite(
                'AdminOrders'
            ) . '&vieworder';
    }

    /**
     * @param \Order $order
     * @param \PrestaShopCollection $orderSlipCollection
     * @param \OrderSlip $lastOrderSlip
     *
     * @return float
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function calculateShippingAmount(
        \Order $order,
        \PrestaShopCollection $orderSlipCollection,
        \OrderSlip $lastOrderSlip,
    ): float {
        $refundedShipment = 0;

        /** @var \OrderSlip $item */
        foreach ($orderSlipCollection as $item) {
            if ($item === $lastOrderSlip) {
                continue;
            }
            $refundedShipment += (float) $item->shipping_cost_amount;
        }

        if (\Tools::getIsset('partialRefundShippingCost') && (float) \Tools::getValue('partialRefundShippingCost') > 0) {
            return (float) \Tools::getValue('partialRefundShippingCost');
        }

        if (\Tools::getIsset('shippingBack') && (\Tools::getValue('shippingBack') === 'on')) {
            $taxRate = (float) $order->carrier_tax_rate;
            $shippingAmount = (float) $order->total_shipping - $refundedShipment;
            $lastOrderSlip->shipping_cost_amount = $shippingAmount;
            $lastOrderSlip->total_shipping_tax_incl = $shippingAmount;
            $lastOrderSlip->total_shipping_tax_excl = $taxRate ? $shippingAmount / (1 + $taxRate / 100.0) : $shippingAmount;
            $lastOrderSlip->update();

            return (float) $order->total_shipping - $refundedShipment;
        }

        return 0;
    }

    /**
     * Deletes order slip and all order slip details related to order slip.
     *
     * @param \Order $order
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    private function deleteOrderSlip(\Order $order): void
    {
        $orderSlipCollection = $order->getOrderSlipsCollection();
        /** @var \OrderSlip $lastOrderSlip */
        $lastOrderSlip = $orderSlipCollection[count($orderSlipCollection) - 1];
        \Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'order_slip_detail` WHERE `id_order_slip` = ' . (int) $lastOrderSlip->id
        );

        $lastOrderSlip->delete();
    }

    /**
     * Quantity refunded or quantity returned is already updated in this stage in Order detail table.
     * These quantities are reverted to previous state.
     *
     * @param \Order $order
     * @param array $quantityList
     *
     * @return void
     *
     * @throws \PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    private function revertOrderDetails(\Order $order, array $quantityList): void
    {
        foreach ($order->getOrderDetailList() as $orderDetail) {
            $orderDetail = new \OrderDetail($orderDetail['id_order_detail']);
            $quantityReturned = $quantityList[$orderDetail->id_order_detail];
            if ($orderDetail->product_quantity < $quantityReturned) {
                $quantityReturned = $orderDetail->product_quantity;
            }

            if (!$quantityReturned) {
                return;
            }

            if (\Tools::getIsset('cancelProduct')) {
                $orderDetail->product_quantity_return = (int) ($orderDetail->product_quantity_return - $quantityReturned);
                $orderDetail->update();

                return;
            }

            $orderDetail->product_quantity_refunded = (int) ($orderDetail->product_quantity_refunded - $quantityReturned);
            $orderDetail->update();
        }
    }

    /**
     * @return string
     */
    public function backofficeOrderJS(): string
    {
        return 'views/js/admin/adyen-backoffice-order-175.js';
    }

    /**
     * @return string
     */
    public function backofficeOrderTemplate(): string
    {
        return 'adyen-backoffice-order-creation-175.tpl';
    }

    /**
     * @return int
     */
    public function getPrecision(): int
    {
        return (int) _PS_PRICE_COMPUTE_PRECISION_;
    }
}
