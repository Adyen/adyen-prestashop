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
use Adyen\PrestaShop\service\modification\exception\NotificationNotFoundException;
use Adyen\Service\Modification;
use Adyen\Util\Currency;
use Db;
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
     * @var Db
     */
    private $db;
    /**
     * @var AbstractLogger
     */
    private $logger;

    /**
     * Refund constructor.
     * @param Modification $modificationClient
     * @param Db $db
     * @param $merchantAccount
     * @param AbstractLogger $logger
     */
    public function __construct(
        Modification $modificationClient,
        Db $db,
        $merchantAccount,
        AbstractLogger $logger = null
    ) {
        $this->modificationClient = $modificationClient;
        $this->db = $db;
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
            $pspReference = $this->getPSPReferenceByOrderId($orderSlip->id_order);
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

    /**
     * @param string $orderId
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws NotificationNotFoundException
     */
    private function getPSPReferenceByOrderId($orderId)
    {
        $results = $this->db->executeS(
            sprintf(
                <<<SQL
select a.pspreference as pspReference
from %sadyen_notification a
inner join %sorders o on a.merchant_reference = o.id_cart
where o.id_order = $orderId
SQL
                ,
                _DB_PREFIX_, _DB_PREFIX_
            )
        );
        if (empty($results)) {
            throw new NotificationNotFoundException("Cannot find a notification for Order ID $orderId");
        }
        $pspReference = $results[0]['pspReference'];
        return $pspReference;
    }
}
