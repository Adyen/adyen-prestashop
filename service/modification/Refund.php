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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\modification;

use AbstractLogger;
use Adyen\AdyenException;
use Adyen\PrestaShop\infra\NotificationRetriever;
use Adyen\PrestaShop\service\modification\exception\NotificationNotFoundException;
use Adyen\Service\Modification;
use Adyen\Util\Currency;
use OrderSlip;
use PrestaShopDatabaseException;

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
     * @var AbstractLogger
     */
    private $logger;

    /**
     * Refund constructor.
     *
     * @param Modification $modificationClient
     * @param NotificationRetriever $notificationRetriever
     * @param $merchantAccount
     * @param AbstractLogger $logger
     */
    public function __construct(
        Modification $modificationClient,
        NotificationRetriever $notificationRetriever,
        $merchantAccount,
        AbstractLogger $logger = null
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
        $amount = $currencyConverter->sanitize($orderSlip->amount, $currency);

        try {
            $pspReference = $this->notificationRetriever->getPSPReferenceByOrderId($orderSlip->id_order);
        } catch (NotificationNotFoundException $e) {
            $this->logger->logError($e->getMessage());
            return false;
        } catch (PrestaShopDatabaseException $e) {
            $this->logger->logError($e->getMessage());
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
            $this->logger->logError($e->getMessage());
            return false;
        }
        return true;
    }
}
