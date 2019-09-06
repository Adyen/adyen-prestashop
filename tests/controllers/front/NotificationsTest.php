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

namespace Adyen\PrestaShop\tests\controllers\front;

use AdyenNotificationsModuleFrontController;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamException;
use org\bovigo\vfs\vfsStreamWrapper;

require_once __DIR__ . '/../../../controllers/front/Notifications.php';

class AdyenNotificationsModuleFrontControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testExceptionLogsError()
    {
        try {
            vfsStreamWrapper::register();
        } catch (vfsStreamException $e) {
            $this->fail($e->getMessage());
        }
        $virtualDirectory = vfsStream::setup();
        $controller = new AdyenNotificationsModuleFrontController($virtualDirectory);
    }
}
