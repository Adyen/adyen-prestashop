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
 * Adyen PrestaShop module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

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
            Db::getInstance()

        );
        $notificationProcessor->doPostProcess();
    }
}
