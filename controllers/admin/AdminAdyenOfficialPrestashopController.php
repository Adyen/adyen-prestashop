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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\exception\GenericLoggedException;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShopBundle\Utils\ZipManager;

require_once _PS_ROOT_DIR_ . '/modules/adyenofficial/vendor/autoload.php';

class AdminAdyenOfficialPrestashopController extends ModuleAdminController
{
    /** @var \Adyen\PrestaShop\service\adapter\classes\Configuration $configuration */
    private $configuration;

    /** @var Adyen\PrestaShop\infra\Crypto */
    private $crypto;

    /** @var VersionChecker */
    private $versionChecker;

    /** @var string $logsDirectory */
    private $logsDirectory;
}
