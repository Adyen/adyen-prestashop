<?php

namespace AdyenPayment\Classes\Version\Contract;

use Order;

/**
 * Interface VersionHandler
 */
interface VersionHandler
{
    /**
     * Contains hooks that differs from versions.
     *
     * @return array
     */
    public function hooks(): array;

    /**
     * @return string
     */
    public function tabLink(): string;

    /**
     * @return string
     */
    public function tabContent(): string;

    /**
     * Returns refunded amount for refund event.
     *
     * @param \Order $order
     *
     * @return float
     */
    public function getRefundedAmount(\Order $order): float;

    /**
     * @param \Order $order
     * @param array $quantityList
     *
     * @return void
     */
    public function rollbackOrderSlipAdd(\Order $order, array $quantityList = []): void;

    /**
     * Returns total refunded amount for order.
     *
     * @param \Order $order
     *
     * @return float
     */
    public function getRefundedAmountOnPresta(\Order $order): float;

    /**
     * When refund comes from Adyen portal, order detail is updated.
     *
     * @param \OrderDetail $orderDetail
     * @param float $amount
     * @param float $amountWithoutTac
     * @param int $quantityRefunded
     *
     * @return void
     */
    public function updateOrderDetail(
        \OrderDetail $orderDetail,
        float $amount,
        float $amountWithoutTac,
        int $quantityRefunded,
    ): void;

    /**
     * Gets refunded amount for specific order detail.
     *
     * @param \OrderDetail $orderDetail
     *
     * @return float
     */
    public function getRefundedAmountForOrderDetail(\OrderDetail $orderDetail): float;

    /**
     * Calculates quantity of specific order detail to add in refund.
     *
     * @param \OrderDetail $orderDetail
     * @param int $quantityRefunded
     *
     * @return int
     */
    public function calculateQuantityToAdd(\OrderDetail $orderDetail, int $quantityRefunded): int;

    /**
     * Return order detail to previous state.
     *
     * @param \OrderDetail $orderDetail
     * @param array $details
     *
     * @return void
     */
    public function rollbackOrderDetail(\OrderDetail $orderDetail, array $details): void;

    /**
     * @param string $merchantReference
     *
     * @return string
     */
    public function getOrderUrl(string $merchantReference): string;

    /**
     * @return string
     */
    public function backofficeOrderJS(): string;

    /**
     * @return string
     */
    public function backofficeOrderTemplate(): string;

    /**
     * @return int
     */
    public function getPrecision(): int;
}
