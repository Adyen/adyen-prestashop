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

namespace Adyen\PrestaShop\infra;

use Adyen\PrestaShop\service\modification\exception\NotificationNotFoundException;
use Db;
use PrestaShopDatabaseException;

class NotificationRetriever
{
    /**
     * @var Db
     */
    private $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @param string $orderId
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws NotificationNotFoundException
     */
    public function getPSPReferenceByOrderId($orderId)
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
        return $results[0]['pspReference'];
    }
}