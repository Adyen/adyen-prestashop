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

namespace Adyen\PrestaShop\service\modification;

use Adyen\AdyenException;
use Adyen\PrestaShop\infra\NotificationRetriever;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\modification\exception\NotificationNotFoundException;
use Adyen\Service\Modification;
use Adyen\Util\Currency;
use OrderSlip;
use PrestaShopDatabaseException;
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
     * @var NotificationRetriever
     */
    private $notificationRetriever;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderAdapter
     */
    private $orderAdapter;

    /**
     * Refund constructor.
     *
     * @param Modification $modificationClient
     * @param NotificationRetriever $notificationRetriever
     * @param $merchantAccount
     * @param OrderAdapter $orderAdapter
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Modification $modificationClient,
        NotificationRetriever $notificationRetriever,
        $merchantAccount,
        OrderAdapter $orderAdapter,
        LoggerInterface $logger = null
    ) {
        $this->modificationClient = $modificationClient;
        $this->notificationRetriever = $notificationRetriever;
        $this->merchantAccount = $merchantAccount;
        $this->orderAdapter = $orderAdapter;
        $this->logger = $logger;
    }

    /**
     * @param OrderSlip $orderSlip
     * @param string $currency
     *
     * @return bool
     */
    public function request(OrderSlip $orderSlip, $currency)
    {
        $currencyConverter = new Currency();

        $fullRefundAmount = $orderSlip->amount;

        // In case shipping cost amount is not empty add shipping costs to the order slip amount
        if (!empty($orderSlip->shipping_cost)) {
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

        try {
            $pspReference = $this->notificationRetriever->getPSPReferenceByOrderId($orderSlip->id_order);
        } catch (NotificationNotFoundException $e) {
            $this->logger->error($e->getMessage());
            return false;
        } catch (PrestaShopDatabaseException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
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
