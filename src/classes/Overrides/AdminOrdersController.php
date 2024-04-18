<?php

namespace AdyenPayment\Classes\Overrides;

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionDetailsService;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\Order;

class AdminOrdersController
{
    private const ADYEN_GRID_PSP_REFERENCE_TEMPLATE =
        'adyenofficial/views/templates/admin/adyen_grid_psp_reference/grid_psp_reference.tpl';
    private const ADYEN_GRID_PAYMENT_METHOD_TEMPLATE =
        'adyenofficial/views/templates/admin/adyen_grid_payment_method/grid_payment_method.tpl';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        Bootstrap::init();
    }

    /**
     * Inserts the Adyen order grid column.
     *
     * @param array $field_list
     * @param string $title
     * @param string $callback
     * @param string $keyColumnName
     * @param string $newColumnName
     *
     * @return array
     */
    public function insertAdyenPaymentColumn(
        array $field_list,
        string $title,
        string $callback,
        string $keyColumnName,
        string $newColumnName
    ): array {
        $pspReferenceElement = [
            'title' => $title,
            'align' => 'text-centre',
            'filter_key' => 'a!id_order',
            'callback' => $callback
        ];

        return $this->insertElementIntoArrayAfterSpecificKey(
            $field_list,
            $keyColumnName,
            [$newColumnName => $pspReferenceElement]
        );
    }

    /**
     * @param string $orderId
     * @param \Context $context
     *
     * @return string
     *
     * @throws \SmartyException
     * @throws \Exception
     */
    public function getOrderPspReference(string $orderId, \Context $context): string
    {
        $order = new Order($orderId);
        $transactionDetails = $this->getTransactionDetails($order);
        $authorisationDetail = $transactionDetails[array_search(
            \Adyen\Webhook\EventCodes::AUTHORISATION,
            array_column($transactionDetails, 'eventCode'),
            true
        )];
        $pspReference = $order->module === 'adyenofficial' && !empty($transactionDetails) ? $authorisationDetail['pspReference'] : '--';

        $context->smarty->assign(
            [
                'orderId' => $orderId,
                'pspReference' => $pspReference
            ]
        );

        return $context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::ADYEN_GRID_PSP_REFERENCE_TEMPLATE,
            $context->smarty
        )->fetch();
    }

    /**
     * @param string $orderId
     * @param \Context $context
     *
     * @return string
     *
     * @throws \SmartyException
     * @throws \Exception
     */
    public function getOrderPaymentMethod(string $orderId, \Context $context): string
    {
        $order = new Order($orderId);
        $transactionDetails = $this->getTransactionDetails($order);
        $lastItem = end($transactionDetails);
        $paymentMethod = $order->module === 'adyenofficial' && !empty($transactionDetails) ? $lastItem['paymentMethodType'] : '--';

        $context->smarty->assign(
            [
                'orderId' => $orderId,
                'paymentMethod' => $paymentMethod
            ]
        );

        return $context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::ADYEN_GRID_PAYMENT_METHOD_TEMPLATE,
            $context->smarty
        )->fetch();
    }

    /**
     * Insert a value or key/value pair after a specific key in an array.
     * If key doesn't exist, value is appended to the end of the array.
     *
     * @param array $elementInformation
     * @param string $key
     * @param array $new
     *
     * @return array
     */
    private function insertElementIntoArrayAfterSpecificKey(array $elementInformation, string $key, array $new): array
    {
        $keys = array_keys($elementInformation);
        $index = array_search($key, $keys, true);
        $position = false === $index ? count($elementInformation) : $index + 1;

        return array_merge(
            array_slice($elementInformation, 0, $position),
            $new,
            array_slice($elementInformation, $position)
        );
    }

    /**
     * @param Order $order
     *
     * @return array
     *
     * @throws Exception
     */
    private function getTransactionDetails(Order $order): array
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
    private function getTransactionDetailsService(string $storeId): TransactionDetailsService
    {
        return StoreContext::doWithStore(
            $storeId,
            [ServiceRegister::getInstance(), 'getService'],
            [TransactionDetailsService::class]
        );
    }
}
