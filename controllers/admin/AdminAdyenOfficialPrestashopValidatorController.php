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
use PrestaShop\PrestaShop\Adapter\CoreException;

require_once _PS_ROOT_DIR_ . '/modules/adyenofficial/vendor/autoload.php';

class AdminAdyenOfficialPrestashopValidatorController extends ModuleAdminController
{
    /** @var \Adyen\PrestaShop\service\adapter\classes\Configuration $configuration */
    private $configuration;

    /** @var Adyen\PrestaShop\infra\Crypto */
    private $crypto;

    /** @var VersionChecker */
    private $versionChecker;

    /** @var string $logsDirectory */
    private $logsDirectory;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws CoreException|PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Render the log-fetcher template
     *
     * @return false|string
     * @throws SmartyException
     */
    public function renderView()
    {
    }
}
