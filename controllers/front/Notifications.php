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

use Adyen\PrestaShop\controllers\FrontController;

class AdyenNotificationsModuleFrontController extends FrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->context = \Context::getContext();
        $adyenHelperFactory = new \Adyen\PrestaShop\service\Adyen\Helper\DataFactory();
        $this->helper_data = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );

        $this->helper_data->startSession();
    }

    public function init()
    {
        parent::init();
        var_dump('something');
    }

    public function postProcess()
    {
        $this->ajaxRender(json_encode(['key' => 'value']));
    }
}