<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionDetailsService;
use Adyen\Core\Infrastructure\ServiceRegister;
use Exception;
use Order;

/**
 * Class TransactionDetailsHandler
 *
 * @package AdyenPayment\Classes\Services
 */
class TransactionDetailsHandler
{
    /**
     * @param Order $order
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getTransactionDetails(Order $order): array
    {
        return StoreContext::doWithStore(
            (string)$order->id_shop,
            [self::getTransactionDetailsService((string)$order->id_shop), 'getTransactionDetails'],
            [(string)$order->id_cart, (string)$order->id_shop]
        );
    }

    /**
     * @param string $storeId
     *
     * @return TransactionDetailsService
     *
     * @throws Exception
     */
    private static function getTransactionDetailsService(string $storeId
    ): TransactionDetailsService {
        return StoreContext::doWithStore(
            $storeId,
            [ServiceRegister::getInstance(), 'getService'],
            [TransactionDetailsService::class]
        );
    }
}
