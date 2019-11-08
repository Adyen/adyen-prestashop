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

namespace Adyen\PrestaShop\controllers;

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use PrestaShopException;

abstract class FrontController extends \ModuleFrontController
{
    /**
     * @var \Adyen\PrestaShop\helper\Data
     */
    protected $helperData;

    public function __construct()
    {
        parent::__construct();
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->helperData->startSession();
    }

    /**
     * @param null $value
     * @param null $controller
     * @param null $method
     * @throws PrestaShopException
     */
    protected function ajaxRender($value = null, $controller = null, $method = null)
    {
        header('content-type: application/json; charset=utf-8');
        if ($this->helperData->isPrestashop16()) {
            $this->ajax = true;
            parent::ajaxDie($value, $controller, $method);
        } else {
            parent::ajaxRender($value, $controller, $method);
            exit;
        }
    }
}
