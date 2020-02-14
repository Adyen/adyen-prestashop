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
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\controllers;

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

abstract class FrontController extends \ModuleFrontController
{
    /**
     * @var \Adyen\PrestaShop\helper\Data
     */
    protected $helperData;

    /**
     * @var \Adyen\PrestaShop\application\VersionChecker
     */
    protected  $versionChecker;

    /**
     * @var \Adyen\PrestaShop\service\Logger
     */
    protected $logger;

    /**
     * FrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->versionChecker = ServiceLocator::get('Adyen\PrestaShop\application\VersionChecker');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
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
        if ($this->versionChecker->isPrestaShop16()) {
            $this->ajax = true;
            parent::ajaxDie($value, $controller, $method);
        } else {
            parent::ajaxRender($value, $controller, $method);
            exit;
        }
    }
}
