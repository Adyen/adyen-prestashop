<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Webhook\PaymentStates;

/**
 * Class AdyenOrderStatusMapping
 *
 * @package AdyenPayment\Classes\Utility
 */
class AdyenOrderStatusMapping
{
    /**
     * Processing in progress string constant.
     */
    public const PRESTA_PROCESSING_IN_PROGRESS = 'Processing in progress';
    /**
     * Pending string constant.
     */
    public const PRESTA_PENDING = 'Pending';
    /**
     * Payment accepted string constant.
     */
    public const PRESTA_PAYMENT_ACCEPTED = 'Payment accepted';
    /**
     * Payment error string constant.
     */
    public const PRESTA_PAYMENT_ERROR = 'Payment error';
    /**
     * Refunded string constant.
     */
    public const PRESTA_REFUNDED = 'Refunded';
    /**
     * Partially refunded string constant.
     */
    public const PRESTA_PARTIALLY_REFUNDED = 'Partially refunded';
    /**
     * Cancelled string constant.
     */
    public const PRESTA_CANCELED = 'Canceled';
    /**
     * On backorder string constant.
     */
    public const PRESTA_ON_BACKORDER = 'On backorder (not paid)';
    /**
     * Chargeback string constant.
     */
    public const PRESTA_CHARGEBACK = 'Chargeback';

    /**
     * @var int[]
     */
    private static $prestaMap;

    /**
     * @var array
     */
    private static $statusMap;

    /**
     * @return int[]
     */
    public static function getDefaultOrderStatusMap(): array
    {
        if (!static::$prestaMap) {
            static::$prestaMap = static::mapPrestaStatuses();
        }

        return static::$prestaMap;
    }

    /**
     * @param $status
     *
     * @return mixed
     */
    public static function getPrestaShopOrderStatusId($status)
    {
        return static::getStatusMap()[$status];
    }

    /**
     * @return array
     */
    private static function mapPrestaStatuses(): array
    {
        return [
            PaymentStates::STATE_IN_PROGRESS => static::getPrestaShopOrderStatusId(self::PRESTA_PROCESSING_IN_PROGRESS)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_PENDING => static::getPrestaShopOrderStatusId(self::PRESTA_PENDING)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_PAID => static::getPrestaShopOrderStatusId(self::PRESTA_PAYMENT_ACCEPTED)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_FAILED => static::getPrestaShopOrderStatusId(self::PRESTA_PAYMENT_ERROR)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_REFUNDED => static::getPrestaShopOrderStatusId(self::PRESTA_REFUNDED)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_PARTIALLY_REFUNDED => static::getPrestaShopOrderStatusId(
                    self::PRESTA_PARTIALLY_REFUNDED
                ) ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_CANCELLED => static::getPrestaShopOrderStatusId(self::PRESTA_CANCELED)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::STATE_NEW => static::getPrestaShopOrderStatusId(self::PRESTA_ON_BACKORDER)
                ?? array_values(self::getStatusMap())[0],
            PaymentStates::CHARGE_BACK => static::getPrestaShopOrderStatusId(self::PRESTA_CHARGEBACK)
                ?? array_values(self::getStatusMap())[0]
        ];
    }

    /**
     * @return array
     */
    private static function getStatusMap(): array
    {
        if (!static::$statusMap) {
            static::$statusMap = array_column(
                \OrderState::getOrderStates(1),
                'id_order_state',
                'name'
            );
        }

        return static::$statusMap;
    }
}
