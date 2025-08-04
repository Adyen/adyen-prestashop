<?php

namespace AdyenPayment\Classes\Services;

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionHistoryService;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\SessionService;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
use Currency as PrestaCurrency;
use Db;
use Exception;
use Module;
use Order;
use OrderDetail;
use OrderSlip;
use PrestaShopDatabaseException;
use PrestaShopException;
use Tools;

/**
 * Class RefundHandler
 *
 * @package AdyenPayment\Classes\Utility
 */
class RefundHandler
{
    /**
     * Sends refund request to Adyen.
     * Order slip is already created at this point.
     *
     * @param Order $order
     *
     * @param array $quantityList
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws InvalidMerchantReferenceException
     * @throws RepositoryClassException
     * @throws Exception
     */
    public static function handleRefund(Order $order, array $quantityList): void
    {
        Bootstrap::init();

        $transactionDetails = TransactionDetailsHandler::getTransactionDetails($order);
        if (empty($transactionDetails)) {
            return;
        }

        $currency = new PrestaCurrency($order->id_currency);
        $amount = self::versionHandler()->getRefundedAmount($order);
        $response = AdminAPI::get()->refund((string)$order->id_shop)->handle(
            (string)$order->id_cart,
            $amount,
            $currency->iso_code
        );

        if (!$response->isSuccessful()) {
            self::versionHandler()->rollbackOrderSlipAdd($order, $quantityList);
            self::setErrorMessage(
                Module::getInstanceByName('adyenofficial')->l(
                    'Refund request failed. Please check Adyen configuration. Reason: '
                )
                . $response->toArray()['errorMessage'] ?? ''
            );

            Tools::redirect(self::orderService()->getOrderUrl((string)$order->id_cart));

            return;
        }

        self::setSuccessMessage(
            Module::getInstanceByName('adyenofficial')->l('Refund request successfully sent to Adyen.')
        );
    }

    /**
     * @param Order $order
     * @param float $amount
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws RepositoryClassException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    public static function handleAdyenSuccessRefund(Order $order, float $amount): void
    {
        Bootstrap::init();
        $currency = new PrestaCurrency($order->id_currency);
        $transactionHistory = self::getTransactionHistoryService()->getTransactionHistory($order->id_cart);
        $refundedOnAdyen = $transactionHistory->getTotalAmountForEventCode('REFUND');
        $refundedOnPresta = self::versionHandler()->getRefundedAmountOnPresta($order);
        if ($refundedOnAdyen->getPriceInCurrencyUnits() > Amount::fromFloat(
                $refundedOnPresta,
                Currency::fromIsoCode($currency->iso_code)
            )->getPriceInCurrencyUnits()) {
            self::refundOnPresta($order, $amount);
        }
    }

    /**
     * @param Order $order
     * @param float $amount
     *
     * @return void
     *
     * @throws InvalidCurrencyCode
     * @throws RepositoryClassException
     * @throws Exception
     */
    public static function handleAdyenFailRefund(Order $order, float $amount)
    {
        Bootstrap::init();

        self::rollbackRefundOnPresta($order, $amount);
    }

    /**
     * @param Order $order
     * @param float $amount
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws InvalidCurrencyCode
     */
    private static function rollbackRefundOnPresta(Order $order, float $amount): void
    {
        $orderSlip = self::findOrderSlipToDelete($order, $amount);

        if (!$orderSlip) {
            return;
        }

        foreach ($orderSlip->getProducts() as $product) {
            $orderDetail = new OrderDetail((int)$product['id_order_detail']);
            $orderSlipDetail = self::getOrderSlipDetail($orderSlip->id, $orderDetail->id);
            if (empty($orderSlipDetail)) {
                continue;
            }

            $details = $orderSlipDetail[0];
            self::versionHandler()->rollbackOrderDetail($orderDetail, $details);
        }

        self::removeOrderSlipDetails($orderSlip->id);
        $orderSlip->delete();
    }

    /**
     * @param int $orderSlipId
     *
     * @return void
     */
    private static function removeOrderSlipDetails(int $orderSlipId): void
    {
        Db::getInstance()->delete('order_slip_detail', 'id_order_slip = ' . $orderSlipId);
    }

    /**
     * @param int $orderSlipId
     * @param int $orderDetailId
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    private static function getOrderSlipDetail(int $orderSlipId, int $orderDetailId): array
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            ($orderDetailId ? 'SELECT `product_quantity`, `amount_tax_incl`,`amount_tax_excl`' : 'SELECT *') .
            'FROM `' . _DB_PREFIX_ . 'order_slip_detail`'
            . ($orderSlipId ? ' WHERE `id_order_slip` = ' . (int)($orderSlipId) : '')
            . ($orderDetailId ? ' AND `id_order_detail` = ' . (int)($orderDetailId) : '')
        );
    }


    /**
     * @param Order $order
     * @param float $amount
     *
     * @return OrderSlip|null
     *
     * @throws InvalidCurrencyCode
     */
    private static function findOrderSlipToDelete(Order $order, float $amount): ?OrderSlip
    {
        $currency = new PrestaCurrency($order->id_currency);

        foreach ($order->getOrderSlipsCollection()->getResults() as $item) {
            $orderSlipAmount = Amount::fromFloat(
                (float)$item->amount + (float)$item->shipping_cost_amount,
                Currency::fromIsoCode($currency->iso_code)
            )->getPriceInCurrencyUnits();

            if ((float)$orderSlipAmount === $amount) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param Order $order
     * @param float $amount
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     */
    private static function refundOnPresta(Order $order, float $amount): void
    {
        $orderSlip = self::createOrderSlip($order, $amount);
        $taxRate = (float)$order->carrier_tax_rate;

        foreach ($order->getOrderDetailList() as $detail) {
            $orderDetail = new OrderDetail($detail['id_order_detail']);
            $refundableAmountForOrderLine = $orderDetail->total_price_tax_incl - self::versionHandler(
                )->getRefundedAmountForOrderDetail($orderDetail);
            if ($refundableAmountForOrderLine) {
                $amountToRefundForOrderLine = min($refundableAmountForOrderLine, $amount);
                $amount -= $amountToRefundForOrderLine;
                $amountWithoutTax = $taxRate > 0 ? $amountToRefundForOrderLine / (1 + $taxRate / 100.0) : $amountToRefundForOrderLine;
                $quantityRefunded = (int)ceil($amountToRefundForOrderLine / (float)$orderDetail->unit_price_tax_incl);
                $quantityToAdd = self::versionHandler()->calculateQuantityToAdd($orderDetail, $quantityRefunded);
                self::addOrderSlipDetail(
                    $orderSlip->id,
                    $orderDetail,
                    $quantityToAdd,
                    $amountToRefundForOrderLine,
                    $amountWithoutTax
                );
                self::versionHandler()->updateOrderDetail(
                    $orderDetail,
                    $amountToRefundForOrderLine,
                    $amountWithoutTax,
                    $quantityToAdd
                );
            }

            if (!$amount) {
                break;
            }
        }
    }

    /**
     * @param int $orderSlipId
     * @param OrderDetail $orderDetail
     * @param int $quantity
     * @param float $amount
     * @param float $amountWithoutTax
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     */
    private static function addOrderSlipDetail(
        int $orderSlipId,
        OrderDetail $orderDetail,
        int $quantity,
        float $amount,
        float $amountWithoutTax
    ): void {
        Db::getInstance()->insert('order_slip_detail', [
            'id_order_slip' => (int)$orderSlipId,
            'id_order_detail' => $orderDetail->id,
            'product_quantity' => $quantity,
            'unit_price_tax_excl' => (float)$orderDetail->unit_price_tax_excl,
            'unit_price_tax_incl' => (float)$orderDetail->unit_price_tax_incl,
            'total_price_tax_incl' => $amount,
            'amount_tax_incl' => $amount,
            'total_price_tax_excl' => $amountWithoutTax,
            'amount_tax_excl' => $amountWithoutTax
        ]);
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private static function createOrderSlip(Order $order, float $amount): OrderSlip
    {
        $refundableProducts = (float)$order->total_products_wt - self::getRefundedProducts($order);
        $totalProducts = $amount;
        $totalShipping = 0;

        if ($refundableProducts < $amount) {
            $totalProducts = $refundableProducts;
            $totalShipping = $amount - $totalProducts;
        }
        $taxRate = (float)$order->carrier_tax_rate;
        $orderSlip = new OrderSlip();
        $orderSlip->id_order = $order->id;
        $orderSlip->id_customer = $order->id_customer;
        $orderSlip->conversion_rate = $order->conversion_rate;
        $orderSlip->total_shipping_tax_incl = $totalShipping;
        $orderSlip->total_products_tax_incl = $totalProducts;
        $orderSlip->total_shipping_tax_excl = $taxRate ? $totalShipping / (1 + $taxRate / 100.0) : $totalShipping;
        $orderSlip->total_products_tax_excl = $taxRate ? $totalProducts / (1 + $taxRate / 100.0) : $totalProducts;
        $orderSlip->amount = $totalProducts;
        $orderSlip->shipping_cost_amount = $totalShipping;
        $orderSlip->add();

        return $orderSlip;
    }

    /**
     * Gets refunded products amount.
     *
     * @param Order $order
     *
     * @return float
     */
    private static function getRefundedProducts(Order $order): float
    {
        $amount = 0;

        foreach ($order->getOrderSlipsCollection()->getResults() as $item) {
            $amount += $item->amount;
        }

        return $amount;
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private static function setSuccessMessage(string $message): void
    {
        SessionService::set('successMessage', $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private static function setErrorMessage(string $message): void
    {
        SessionService::set('errorMessage', $message);
    }

    /**
     * @return VersionHandler
     */
    private static function versionHandler(): VersionHandler
    {
        return ServiceRegister::getService(VersionHandler::class);
    }

    /**
     * @return OrderService
     */
    private static function orderService(): OrderService
    {
        return ServiceRegister::getService(OrderService::class);
    }

    /**
     * @return TransactionHistoryService
     */
    private static function getTransactionHistoryService(): TransactionHistoryService
    {
        return ServiceRegister::getService(TransactionHistoryService::class);
    }
}
