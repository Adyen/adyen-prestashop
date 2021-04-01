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

namespace Adyen\PrestaShop\service;

use Adyen\PrestaShop\infra\NotificationRetriever;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\modification\Refund;
use Configuration as PrestaShopConfiguration;
use Currency;
use Order as PrestaShopOrder;
use OrderSlip;
use PrestaShopException;

class RefundService
{

    /**
     * @var Modification
     */
    private $modificationService;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * RefundService constructor.
     *
     * @param Modification $modificationService
     * @param Logger $logger
     */
    public function __construct(
        Modification $modificationService,
        Logger $logger
    ) {
        $this->modificationService = $modificationService;
        $this->logger = $logger;
    }

    /**
     * @param PrestaShopOrder $order
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function refund(PrestaShopOrder $order)
    {
        $refundService = new Refund(
            $this->modificationService,
            PrestaShopConfiguration::get('ADYEN_MERCHANT_ACCOUNT'),
            new OrderAdapter(),
            new OrderPaymentService(),
            $this->logger
        );

        /** @var OrderSlip $orderSlip */
        $orderSlip = $order->getOrderSlipsCollection()
                           ->orderBy('date_upd', 'desc')
                           ->getFirst();
        $currency = Currency::getCurrency($order->id_currency);
        if ($orderSlip) {
            return $refundService->request($orderSlip, $currency['iso_code']);
        } else {
            $this->logger->error('Refund occurred without a credit slip.');
        }
        
        return false;
    }
}
