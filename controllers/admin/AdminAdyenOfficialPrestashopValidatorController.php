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
use Adyen\PrestaShop\exception\ModuleValidationException;
use Adyen\PrestaShop\helper\Data;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use PrestaShop\PrestaShop\Adapter\CoreException;

require_once _PS_ROOT_DIR_ . '/modules/adyenofficial/vendor/autoload.php';

class AdminAdyenOfficialPrestashopValidatorController extends ModuleAdminController
{
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
        // Required to automatically call the renderView function
        $this->display = 'view';
        $this->bootstrap = true;
        $this->toolbar_title[] = 'Validator';
        parent::__construct();

        if ((string)Tools::getValue('validate')) {
            if (!$this->validateModule()) {
                // Exception must be thrown since any other return value will be overrided by prestashop
                throw new ModuleValidationException();
            }
        }
    }

    /**
     * Render the log-fetcher template
     *
     * @return false|string
     * @throws SmartyException
     */
    public function renderView()
    {
        $smartyVariables = array(
            'logo' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->module->name . '/views/img/adyen.png'),
            'validateUrl' => $this->getValidateUrl()
        );
        $this->addCSS('modules/' . $this->module->name . '/views/css/adyen_admin.css');

        // Passing variables in this call (instead of assign()) required for 1.6
        $tpl = $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/validator.tpl',
            null,
            null,
            $smartyVariables
        );

        return $tpl->fetch();
    }

    /**
     * @return bool
     */
    private function validateModule()
    {
        $invalidConfigs = array();
        foreach (AdyenOfficial::ADYEN_CONFIG_NAMES as $key) {
            // TODO: Check using shop id
            if (!Configuration::hasKey($key)) {
                $invalidConfigs[] = $key;
            }
        }

        return empty($invalidConfigs);
    }

    /**
     * Get the url accessed when the button is clicked, to download the zip file
     *
     * @return string
     */
    private function getValidateUrl()
    {
        $adminUrl = Tools::getAdminUrl('admin-dev/index.php?controller=AdminAdyenOfficialPrestashopValidator&token=');
        $token = Tools::getAdminTokenLite('AdminAdyenOfficialPrestashopValidator');

        return $adminUrl . $token;
    }
}
