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


use PrestaShopException;

abstract class FrontController extends \ModuleFrontController
{
    /**
     * @var \Adyen\PrestaShop\helper\Data
     */
    protected $helperData;

    /**
     * @param null $value
     * @param null $controller
     * @param null $method
     * @throws PrestaShopException
     */
    protected function ajaxRender($value = null, $controller = null, $method = null)
    {
        if ($this->helperData->isPrestashop16()) {
            parent::ajaxDie($value, $controller, $method);
        } else {
            parent::ajaxRender($value, $controller, $method);
        }
        exit;
    }
}