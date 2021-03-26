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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\modification;

use Adyen\AdyenException;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\OrderPaymentService;
use Adyen\Service\Modification;
use Adyen\Util\Currency;
use OrderSlip;
use Psr\Log\LoggerInterface;

class Refund
{
    /**
     * @var Modification
     */
    private $modificationClient;

    /**
     * @var string
     */
    private $merchantAccount;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderAdapter
     */
    private $orderAdapter;

    /**
     * @var OrderPaymentService
     */
    private $orderPaymentService;

    /**
     * Refund constructor.
     *
     * @param Modification $modificationClient
     * @param $merchantAccount
     * @param OrderAdapter $orderAdapter
     * @param OrderPaymentService $orderPaymentService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Modification $modificationClient,
        $merchantAccount,
        OrderAdapter $orderAdapter,
        OrderPaymentService $orderPaymentService,
        LoggerInterface $logger = null
    ) {
        $this->modificationClient = $modificationClient;
        $this->merchantAccount = $merchantAccount;
        $this->orderAdapter = $orderAdapter;
        $this->logger = $logger;
        $this->orderPaymentService = $orderPaymentService;
    }

    /**
     * @param OrderSlip $orderSlip
     * @param string $currency
     *
     * @return bool
     * @throws \PrestaShopException
     */
    public function request(OrderSlip $orderSlip, $currency)
    {
        $currencyConverter = new Currency();

        $fullRefundAmount = $orderSlip->amount;

        // If admin wants to include shipping, add shipping costs to the order slip amount
        if ($orderSlip->shipping_cost === '1') {
            $fullRefundAmount += $orderSlip->shipping_cost_amount;
        }

        $order = $this->orderAdapter->getOrderByOrderSlipId($orderSlip->id);

        // Cap the amount to be refunded due to an issue on prestashop which may cause the refund amount to exceed
        // the total paid amount
        // TODO: Remove this check once https://github.com/PrestaShop/PrestaShop/issues/18319 has been fixed
        if ($fullRefundAmount > $order->total_paid) {
            $fullRefundAmount = $order->total_paid;
        }

        $amount = $currencyConverter->sanitize($fullRefundAmount, $currency);

        $orderPayment = $this->orderPaymentService->getAdyenOrderPayment($order);
        if (!$orderPayment || empty($orderPayment->transaction_id)) {
            $this->logger->error(sprintf('Unable to get order payment linked to order (%s) OR
             order payment has an empty transaction_id', $order->id));
            return false;
        }

        $pspReference = $orderPayment->transaction_id;
        try {
            $this->modificationClient->refund(
                array(
                    'originalReference' => $pspReference,
                    'modificationAmount' => array(
                        'value' => $amount,
                        'currency' => $currency
                    ),
                    'reference' => $orderSlip->id,
                    'merchantAccount' => $this->merchantAccount
                )
            );
        } catch (AdyenException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return true;
    }
}
