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

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

class AdminAdyenPrestashopCronController extends \ModuleAdminController
{
    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws \Adyen\AdyenException
     */
    public function __construct()
    {

        $adyenHelperFactory = new \Adyen\PrestaShop\service\helper\DataFactory();
        $this->helperData = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );

        if (\Tools::getValue('token') != $this->helperData->decrypt(\Configuration::get('ADYEN_CRONJOB_TOKEN'))) {
            die('Invalid token');
        }

        $this->context = \Context::getContext();

        parent::__construct();
        $this->postProcess();
        die();
    }

    /**
     *
     */
    public function postProcess()
    {
        $notificationProcessor = new \Adyen\PrestaShop\service\notification\NotificationProcessor(
            $this->helperData,
            Db::getInstance(),
            new \Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter(),
            new \Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter()
        );

        $unprocessedNotifications = $notificationProcessor->getUnprocessedNotifications();

        foreach ($unprocessedNotifications as $unprocessedNotification) {
            // update as processing
            $notificationProcessor->updateNotificationAsProcessing($unprocessedNotification['entity_id']);

            // Add cron message to order
            if ($notificationProcessor->addMessage($unprocessedNotification)) {
                // processing is done
                $notificationProcessor->updateNotificationAsDone($unprocessedNotification['entity_id']);
            } else {
                // processing had some error
                $notificationProcessor->updateNotificationAsNew($unprocessedNotification['entity_id']);
            }
        }
    }
}
