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

namespace Adyen\PrestaShop\service\adapter\classes;

use Adyen\PrestaShop\application\VersionChecker;
use FrontController;

class Controller
{
    /**
     * @var FrontController
     */
    private $controller;

    /**
     * @var VersionChecker
     */
    private $versionChecker;

    public function __construct(VersionChecker $versionChecker)
    {
        $this->versionChecker = $versionChecker;
    }

    /**
     * @param FrontController $controller
     */
    public function setController(FrontController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @param string $id
     * @param string $relativePath
     * @param array $params
     */
    public function registerJavascript($id, $relativePath, array $params = array())
    {
        if ($this->versionChecker->isPrestaShop16()) {
            /** @noinspection PhpDeprecationInspection */
            $this->controller->addJS($relativePath);
        } else {
            $this->controller->registerJavascript($id, $relativePath, $params);
        }
    }

    /**
     * @param string $id
     * @param string $relativePath
     * @param array $params
     */
    public function registerStylesheet($id, $relativePath, $params = array())
    {
        if ($this->versionChecker->isPrestaShop16()) {
            /** @noinspection PhpDeprecationInspection */
            $this->controller->addCSS($relativePath);
        } else {
            $this->controller->registerStylesheet($id, $relativePath, $params);
        }
    }
}
