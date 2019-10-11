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
     * @param string $orderId
     * @param OrderSlip $orderSlip
     * @param string $currency
     * @return bool
     * @throws AdyenException
     * @throws PrestaShopDatabaseException
     */
    public function request($orderId, OrderSlip $orderSlip, $currency)
    {
        $amount = (new Currency())->sanitize($orderSlip->amount, $currency);

        $orderId = pSQL($orderId);
        $results = $this->db->executeS(sprintf(
            <<<SQL
select a.pspreference as pspReference,
   o.id_order as orderId
from %sadyen_notification a
inner join %sorders o on a.merchant_reference = o.id_cart
where o.id_order = $orderId
SQL
            ,
            _DB_PREFIX_, _DB_PREFIX_
        ));
        $this->logger->logDebug($results);
        $this->modificationClient->refund([
            'originalReference' => $results[0]['pspReference'],
            'modificationAmount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'reference' => $orderSlip->id,
            'merchantAccount' => $this->merchantAccount
        ]);
        return true;
    }
}
