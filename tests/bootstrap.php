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

define('_PS_IN_TEST_', true);
define('_PS_ROOT_DIR_', __DIR__ . '/../../..');
define('_PS_MODULE_DIR_', _PS_ROOT_DIR_ . '/tests/resources/modules/');

define('DS', DIRECTORY_SEPARATOR);
$mainDir = dirname(__DIR__) . DS . '..' . DS . '..' . DS;
/** @noinspection PhpIncludeInspection */
require_once($mainDir . 'config' . DS . 'defines.inc.php');
require_once(_PS_CONFIG_DIR_ . 'autoload.php');
require_once(_PS_CONFIG_DIR_ . 'bootstrap.php');
require_once(__DIR__ . DS . '..' . DS . 'vendor' . DS . 'autoload.php');
