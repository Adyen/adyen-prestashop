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
    /**
     * @var Adyen\PrestaShop\service\Logger
     */
    private $logger;

    /**
     * @var Data
     */
    private $helperData;

    /**
     * @var VersionChecker
     */
    private $versionChecker;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws CoreException|PrestaShopException
     */
    public function __construct()
    {
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->versionChecker = ServiceLocator::get('Adyen\PrestaShop\application\VersionChecker');

        // Required to automatically call the renderView function
        $this->display = 'view';
        $this->bootstrap = true;
        $this->toolbar_title[] = 'Validator';

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
        $smartyVariables = array(
            'logo' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->module->name . '/views/img/adyen.png'),
            'validateUrl' => $this->getValidateUrl(),
            'shops' => $this->getShops()
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
     * Called by PrestaShop 1.7
     *
     * @throws ModuleValidationException
     * @throws PrestaShopException
     */
    public function displayAjaxGet()
    {
        $this->validate();
        $this->ajaxRender(json_encode(['Hello' => 1]));
    }

    /**
     * Called by Prestashop 1.6
     *
     * @throws ModuleValidationException
     * @throws PrestaShopException
     */
    public function ajaxProcessget()
    {
        $this->validate();
        $this->ajaxDie(json_encode(['Hello' => 1]));
    }

    /**
     * @throws ModuleValidationException
     */
    private function validate()
    {
        if (!$this->validateModuleConfigs(Tools::getValue('shop')) ||
            !$this->validateModuleTables() ||
            !$this->validateModuleHooks()
        ) {
            // Exception must be thrown since any other return value will be overrided by prestashop
            throw new ModuleValidationException();
        }
    }

    /**
     * Validate that all adyen module configurations exist in the db. If passed, check only for that shop
     *
     * @return bool
     */
    private function validateModuleConfigs($shopId = null)
    {
        $invalidConfigs = array();
        foreach (AdyenOfficial::ADYEN_CONFIG_NAMES as $key) {
            if (!Configuration::hasKey($key, null, null, $shopId)) {
                $invalidConfigs[] = $key;
                $this->logger->error(sprintf('%s configuration not found in ps_configuration table', $key));
            }
        }

        return empty($invalidConfigs);
    }

    /**
     * Validate that all adyen module tables have been created in the db
     *
     * @return bool
     */
    private function validateModuleTables()
    {
        $notificationTable = $this->helperData->tableExists('adyen_notification');
        $responseTable = $this->helperData->tableExists('adyen_payment_response');

        return $notificationTable && $responseTable;
    }

    /**
     * Validate that all hooks required by the module are correctly registered
     *
     * @return bool
     */
    private function validateModuleHooks()
    {
        $invalidHooks = array();
        $version = $this->versionChecker->isPrestaShop16() ? '1.6' : '1.7';
        $adyenHooks = AdyenOfficial::ADYEN_HOOKS[$version];
        $moduleHooks = $this->getPossibleHooksList();

        foreach ($adyenHooks as $adyenHook) {
            $registeredAdyenHook = array_filter($moduleHooks, function ($moduleHook) use ($adyenHook) {
                return $moduleHook['name'] === $adyenHook && $moduleHook['registered'] === true;
            });
            if (empty($registeredAdyenHook)) {
                $invalidHooks[] = $adyenHook;
                $this->logger->error(sprintf('%s hook is not registered', $adyenHook));
            }
        }

        return empty($invalidHooks);
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

    /**
     * Get all shops and return them as id => name
     *
     * @return array
     */
    private function getShops()
    {
        $shops = array();
        foreach (Shop::getShops() as $shop) {
            $shops[$shop['id_shop']] = $shop['name'];
        }

        return $shops;
    }

    /**
     * Return the hooks list where this module can be hooked.
     * Function is a copy of Module::getPossibleHooksList since on 1.6, the registered value is not passed
     *
     * TODO: Remove this once 1.6 support is terminated
     *
     * @return array hooks list
     */
    private function getPossibleHooksList()
    {
        $hooks_list = Hook::getHooks();
        $possible_hooks_list = array();
        $registeredHookList = Hook::getHookModuleList();
        foreach ($hooks_list as &$current_hook) {
            $hook_name = $current_hook['name'];
            $retro_hook_name = Hook::getRetroHookName($hook_name);

            if (is_callable(array($this->module, 'hook' . ucfirst($hook_name))) ||
                is_callable(array($this->module, 'hook' . ucfirst($retro_hook_name)))) {
                $possible_hooks_list[] = array(
                    'id_hook' => $current_hook['id_hook'],
                    'name' => $hook_name,
                    'description' => $current_hook['description'],
                    'title' => $current_hook['title'],
                    'registered' => !empty($registeredHookList[$current_hook['id_hook']][$this->module->id]),
                );
            }
        }

        return $possible_hooks_list;
    }
}
