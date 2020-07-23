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
     * Refund constructor.
     *
     * @param Modification $modificationClient
     * @param NotificationRetriever $notificationRetriever
     * @param $merchantAccount
     * @param LoggerInterface $logger
     */
    public function __construct(
        Modification $modificationClient,
        NotificationRetriever $notificationRetriever,
        $merchantAccount,
        LoggerInterface $logger = null
    ) {
        $this->modificationClient = $modificationClient;
        $this->notificationRetriever = $notificationRetriever;
        $this->merchantAccount = $merchantAccount;
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
        if (!empty($orderSlip->shipping_cost_amount)) {
            $fullRefundAmount += $orderSlip->shipping_cost_amount;
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
