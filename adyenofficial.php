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

/** @noinspection PhpFullyQualifiedNameUsageInspection */
// PrestaShop good practices ask developers to check if PrestaShop is loaded
// before running any other PHP code, which breaks a PSR1 element.
// Also, the main class is not in a namespace, which breaks another element.
// phpcs:disable PSR1.Files.SideEffects,PSR1.Classes.ClassDeclaration,Squiz.Classes.ValidClassName

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_ROOT_DIR_ . '/modules/adyenofficial/vendor/autoload.php';

// this file cannot contain the `use` operator for PrestaShop 1.6
class AdyenOfficial extends PaymentModule
{
    /**
     * @var string
     */
    public $display;

    /**
     * @var int
     */
    public $is_eu_compatible;

    /**
     * @var string
     */
    public $meta_title;

    /**
     * @var Adyen\PrestaShop\helper\Data
     */
    private $helper_data;

    /**
     * @var Adyen\PrestaShop\model\Hashing
     */
    private $hashing;

    /**
     * @var Adyen\PrestaShop\application\VersionChecker
     */
    private $versionChecker;

    /**
     * @var \Adyen\PrestaShop\service\Logger
     */
    private $logger;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Language
     */
    private $languageAdapter;

    /**
     * @var Adyen\PrestaShop\infra\Crypto
     */
    private $crypto;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Configuration
     */
    private $configuration;

    /**
     * @var Adyen\PrestaShop\model\AdyenPaymentResponse
     */
    private $adyenPaymentResponseModel;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Country
     */
    private $countryAdapter;

    /**
     * @var Adyen\Util\Currency
     */
    private $currencyUtil;

    /**
     * Adyen constructor.
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function __construct()
    {
        $this->name = 'adyenofficial';
        $this->version = '3.3.0';
        $this->tab = 'payments_gateways';
        $this->author = 'Adyen';
        $this->bootstrap = true;
        $this->display = 'view';
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => _PS_VERSION_);
        $this->currencies = true;

        $this->helper_data = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\helper\Data'
        );

        $this->hashing = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\model\Hashing'
        );

        $this->versionChecker = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\application\VersionChecker'
        );

        $this->logger = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\service\Logger'
        );

        $this->languageAdapter = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\service\adapter\classes\Language'
        );

        $this->crypto = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\infra\Crypto'
        );

        $this->configuration = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\service\adapter\classes\Configuration'
        );

        $this->adyenPaymentResponseModel = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\model\AdyenPaymentResponse'
        );

        $this->countryAdapter = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            'Adyen\PrestaShop\service\adapter\classes\Country'
        );

        $this->currencyUtil = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
            '\Adyen\Util\Currency'
        );

        // start for 1.6
        $this->is_eu_compatible = 1;
        // The need_instance flag indicates whether to load the module's class
        // when displaying the "Modules" page in the back-office
        $this->need_instance = 1;
        // end for 1.6

        parent::__construct();

        $this->dependencies = array();

        $this->meta_title = $this->l('Adyen');
        $this->displayName = $this->l('Adyen');
        $this->description = $this->l('Accept all payments offered by Adyen');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Install script
     *
     * This function is called when
     * when User installs the module or
     * when User resets the module and selects the do not keep the data option
     *
     * @return bool
     */
    public function install()
    {
        if (!$this->versionChecker->isPrestaShopSupportedVersion()) {
            $this->_errors[] = $this->l('Sorry, this module is not compatible with your version.');
            return false;
        }

        // Version 1.6
        if ($this->versionChecker->isPrestaShop16()) {
            if (parent::install() &&
                $this->registerHook('displayPaymentTop') &&
                $this->registerHook('payment') &&
                $this->registerHook('displayPaymentEU') &&
                $this->registerHook('paymentReturn') &&
                $this->registerHook('actionOrderSlipAdd') &&
                $this->registerHook('actionFrontControllerSetMedia') &&
                $this->installTab() &&
                $this->updateCronJobToken() &&
                $this->createAdyenOrderStatuses() &&
                $this->createAdyenDatabaseTables()
            ) {
                return true;
            } else {
                $this->logger->debug('Adyen module: installation failed!');
                return false;
            }
        }

        // Version 1.7 or higher
        if (parent::install() &&
            $this->registerHook('displayPaymentTop') &&
            $this->installTab() &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionOrderSlipAdd') &&
            $this->updateCronJobToken() &&
            $this->createAdyenOrderStatuses() &&
            $this->createAdyenDatabaseTables()
        ) {
            return true;
        } else {
            $this->logger->debug('Adyen module: installation failed!');
            return false;
        }
    }

    /**
     * @return bool
     */
    private function createAdyenDatabaseTables()
    {
        return $this->createAdyenPaymentResponseTable() &&
            $this->createAdyenNotificationTable();
    }

    /**
     * Uninstall script
     *
     * This function is called when
     * when User uninstalls the module or
     * when User resets the module and selects the do not keep the data option
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall() &&
            $this->uninstallTab() &&
            $this->removeAdyenDatabaseTables() &&
            $this->removeConfigurationsFromDatabase();
    }

    /**
     * Reset script
     *
     * This function is called when User resets the module and selects the reset only the parameters option
     *
     * @return bool
     */
    public function reset()
    {
        if ($this->removeConfigurationsFromDatabase() &&
            $this->updateCronJobToken()) {
            return true;
        } else {
            $this->logger->debug('Adyen module: reset failed!');
            return false;
        }
    }

    /**
     * Updating the cron job token
     *
     * @param string $token
     * @return bool
     */
    public function updateCronJobToken($token = '')
    {
        if (empty($token)) {
            $token = $this->crypto->encrypt(Tools::getShopDomainSsl() . time());
        }

        return Configuration::updateValue('ADYEN_CRONJOB_TOKEN', $this->crypto->encrypt($token));
    }

    /**
     * @return bool
     */
    public function createAdyenNotificationTable()
    {
        $db = Db::getInstance();
        $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'adyen_notification` (
            `entity_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT \'Adyen Notification Entity ID\',
            `pspreference` varchar(255) DEFAULT NULL COMMENT \'Pspreference\',
            `original_reference` varchar(255) DEFAULT NULL COMMENT \'Original Reference\',
            `merchant_reference` varchar(255) DEFAULT NULL COMMENT \'Merchant Reference\',
            `event_code` varchar(255) DEFAULT NULL COMMENT \'Event Code\',
            `success` varchar(255) DEFAULT NULL COMMENT \'Success\',
            `payment_method` varchar(255) DEFAULT NULL COMMENT \'Payment Method\',
            `amount_value` varchar(255) DEFAULT NULL COMMENT \'Amount value\',
            `amount_currency` varchar(255) DEFAULT NULL COMMENT \'Amount currency\',
            `reason` varchar(255) DEFAULT NULL COMMENT \'reason\',
            `live` varchar(255) DEFAULT NULL COMMENT \'Send from Live platform of adyen?\',
            `additional_data` text COMMENT \'AdditionalData\',
            `done` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'done\',
            `processing` tinyint(1) DEFAULT \'0\' COMMENT \'Adyen Notification Cron Processing\',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'Created At\',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            COMMENT \'Updated At\',
            PRIMARY KEY (`entity_id`),
            KEY `ADYEN_NOTIFICATION_PSPREFERENCE` (`pspreference`),
            KEY `ADYEN_NOTIFICATION_EVENT_CODE` (`event_code`),
            KEY `ADYEN_NOTIFICATION_PSPREFERENCE_EVENT_CODE` (`pspreference`,`event_code`),
            KEY `ADYEN_NOTIFICATION_MERCHANT_REFERENCE_EVENT_CODE` (`merchant_reference`,`event_code`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT=\'Adyen Notifications\'';

        return $db->execute($query);
    }

    /**
     * @return bool
     */
    public function createAdyenPaymentResponseTable()
    {
        $db = Db::getInstance();
        $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'adyen_payment_response` (
            `entity_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT \'Adyen Payment Entity ID\',
            `id_cart` int(11) DEFAULT NULL COMMENT \'Prestashop cart id\',
            `request_amount` int COMMENT \'Payment amount in the request\',
            `request_currency` varchar(3) COMMENT \'Payment currency in the request\',
            `result_code` varchar(255) DEFAULT NULL COMMENT \'Result code\',
            `response` text COMMENT \'Response\',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'Created At\',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            COMMENT \'Updated At\',
            PRIMARY KEY (`entity_id`),
            KEY `ADYEN_PAYMENT_RESPONSE_ID_CART` (`id_cart`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT=\'Adyen Payment Action\'';

        return $db->execute($query);
    }

    /**
     * Creates new order statuses for the Adyen payment methods and returns true in case of success
     *
     * @return bool
     */
    public function createAdyenOrderStatuses()
    {
        return $this->createWaitingForPaymentOrderStatus() && $this->createPaymentNeedsAttentionOrderStatus();
    }

    /**
     * Create a new order status: "waiting for payment"
     *
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function createWaitingForPaymentOrderStatus()
    {
        if (!Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT')) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Waiting for payment';
            }

            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->color = '#4169E1';
            $order_state->logable = true;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_ . '/img/os/' . Configuration::get('PS_OS_BANKWIRE') . '.gif';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            }

            return Configuration::updateValue('ADYEN_OS_WAITING_FOR_PAYMENT', (int)$order_state->id);
        }

        return true;
    }

    /**
     * Create a new order status: "payment needs attention"
     *
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function createPaymentNeedsAttentionOrderStatus()
    {
        if (!Configuration::get('ADYEN_OS_PAYMENT_NEEDS_ATTENTION')) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Payment needs attention';
            }

            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->color = '#d62424';
            $order_state->logable = true;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_ . '/img/os/' . Configuration::get('PS_OS_BANKWIRE') . '.gif';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            }

            return Configuration::updateValue('ADYEN_OS_PAYMENT_NEEDS_ATTENTION', (int)$order_state->id);
        }

        return true;
    }

    /**
     * Drop all Adyen related database tables
     *
     * @return bool
     */
    private function removeAdyenDatabaseTables()
    {
        $db = Db::getInstance();
        return $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'adyen_notification`') &&
            $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'adyen_payment_response`');
    }

    /**
     * Removes Adyen settings from configuration table
     *
     * @return bool
     */
    private function removeConfigurationsFromDatabase()
    {
        $adyenConfigurationNames = array(
            'ADYEN_MERCHANT_ACCOUNT',
            'ADYEN_INTEGRATOR_NAME',
            'ADYEN_MODE',
            'ADYEN_NOTI_USERNAME',
            'ADYEN_NOTI_PASSWORD',
            'ADYEN_APIKEY_TEST',
            'ADYEN_APIKEY_LIVE',
            'ADYEN_CLIENTKEY_TEST',
            'ADYEN_CLIENTKEY_LIVE',
            'ADYEN_NOTI_HMAC',
            'ADYEN_LIVE_ENDPOINT_URL_PREFIX',
            'ADYEN_CRONJOB_TOKEN',
            'ADYEN_APPLE_PAY_MERCHANT_NAME',
            'ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER',
            'ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID',
            'ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER'
        );

        $result = true;

        foreach ($adyenConfigurationNames as $adyenConfigurationName) {
            if (!Configuration::deleteByName($adyenConfigurationName)) {
                $this->logger->debug("Configuration couldn't be deleted by name: " . $adyenConfigurationName);
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @return bool true if tab is installed
     */
    public function installTab()
    {
        try {
            $tab = new Tab();
            $tab->id_parent = -1; // invisible tab
            $tab->active = 1;
            $tab->name = array();
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = 'Adyen Prestashop Cron';
            }
            $tab->class_name = 'AdminAdyenOfficialPrestashopCron';
            $tab->module = $this->name;
            return $tab->add();
        } catch (PrestaShopDatabaseException $e) {
            return false;
        } catch (PrestaShopException $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function uninstallTab()
    {
        try {
            $id_tab = (int)Tab::getIdFromClassName('AdminAdyenOfficialPrestashopCron');
            if ($id_tab) {
                $tab = new Tab($id_tab);
                return $tab->delete();
            }
        } catch (PrestaShopDatabaseException $e) {
            return false;
        } catch (PrestaShopException $e) {
            return false;
        }
        return false;
    }


    /**
     * shows the configuration page in the back-end
     */

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            // get post values
            $merchant_account = (string)Tools::getValue('ADYEN_MERCHANT_ACCOUNT');
            $integrator_name = (string)Tools::getValue('ADYEN_INTEGRATOR_NAME');
            $mode = (string)Tools::getValue('ADYEN_MODE');
            $notification_username = (string)Tools::getValue('ADYEN_NOTI_USERNAME');
            $notification_password = (string)Tools::getValue('ADYEN_NOTI_PASSWORD');
            $notification_hmac = (string)Tools::getValue('ADYEN_NOTI_HMAC');
            $cron_job_token = Tools::getValue('ADYEN_CRONJOB_TOKEN');
            $api_key_test = Tools::getValue('ADYEN_APIKEY_TEST');
            $api_key_live = Tools::getValue('ADYEN_APIKEY_LIVE');
            $client_key_test = Tools::getValue('ADYEN_CLIENTKEY_TEST');
            $client_key_live = Tools::getValue('ADYEN_CLIENTKEY_LIVE');
            $live_endpoint_url_prefix = (string)Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $apple_pay_merchant_name = Tools::getValue('ADYEN_APPLE_PAY_MERCHANT_NAME');
            $apple_pay_merchant_identifier = Tools::getValue('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER');
            $google_pay_gateway_merchant_id = Tools::getValue('ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID');
            $google_pay_merchant_identifier = Tools::getValue('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER');

            // validating the input
            if (empty($merchant_account) || !Validate::isGenericName($merchant_account)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Merchant Account'));
            }

            if (!Validate::isGenericName($integrator_name) || preg_match('/[^A-Za-z0-9]/', $integrator_name)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Integrator Name'));
            }

            if (empty($notification_username) || !Validate::isGenericName($notification_username)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Notification Username'));
            }

            if ($output == null) {
                Configuration::updateValue('ADYEN_MERCHANT_ACCOUNT', $merchant_account);
                Configuration::updateValue('ADYEN_INTEGRATOR_NAME', $integrator_name);
                Configuration::updateValue('ADYEN_MODE', $mode);
                Configuration::updateValue('ADYEN_NOTI_USERNAME', $notification_username);
                Configuration::updateValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX', $live_endpoint_url_prefix);
                Configuration::updateValue('ADYEN_CLIENTKEY_TEST', $client_key_test);
                Configuration::updateValue('ADYEN_CLIENTKEY_LIVE', $client_key_live);
                Configuration::updateValue('ADYEN_APPLE_PAY_MERCHANT_NAME', $apple_pay_merchant_name);
                Configuration::updateValue('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER', $apple_pay_merchant_identifier);
                Configuration::updateValue('ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID', $google_pay_gateway_merchant_id);
                Configuration::updateValue('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER', $google_pay_merchant_identifier);

                if (!empty($notification_password)) {
                    Configuration::updateValue('ADYEN_NOTI_PASSWORD', $this->crypto->encrypt($notification_password));
                }

                if (!empty($notification_hmac)) {
                    Configuration::updateValue('ADYEN_NOTI_HMAC', $this->crypto->encrypt($notification_hmac));
                }

                if (!empty($cron_job_token)) {
                    Configuration::updateValue('ADYEN_CRONJOB_TOKEN', $this->crypto->encrypt($cron_job_token));
                }

                if (!empty($api_key_test)) {
                    Configuration::updateValue('ADYEN_APIKEY_TEST', $this->crypto->encrypt($api_key_test));
                }

                if (!empty($api_key_live)) {
                    Configuration::updateValue('ADYEN_APIKEY_LIVE', $this->crypto->encrypt($api_key_live));
                }

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayGetStarted() . $this->displayForm();
    }

    /**
     * @return string
     */
    private function displayGetStarted()
    {
        $smartyVariables = array(
            'logo' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/adyen.png'),
            'links' => array(
                array(
                    "label" => "Docs",
                    "url" => "https://docs.adyen.com/plugins/prestashop"
                ),
                array(
                    "label" => "Support",
                    "url" => "https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=78764"
                ),
                array(
                    "label" => "GitHub",
                    "url" => "https://github.com/Adyen/adyen-prestashop/releases"
                ),
                array(
                    "label" => "PrestaShop Marketplace",
                    "url" => "https://addons.prestashop.com/en/payments-gateways-prestashop-modules/" .
                        "48042-adyen-the-payments-platform-built-for-growth.html"
                )
            )
        );

        $this->context->smarty->assign($smartyVariables);

        return $this->display(__FILE__, '/views/templates/front/get-started.tpl');
    }

    /**
     * @return mixed
     */
    public function displayForm()
    {
        $this->context->controller->addCSS('modules/' . $this->name . '/views/css/adyen_admin.css', 'all');

        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form = array(array());

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('General Settings'),
                'image' => '../img/admin/edit.gif'
            ),
            'input' => array(),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        // Merchant account input
        $fields_form[0]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Merchant Account'),
            'name' => 'ADYEN_MERCHANT_ACCOUNT',
            'size' => 20,
            'required' => true,
            'lang' => false,
            'hint' => $this->l(
                'In Adyen backoffice you have a company account with one or more merchantaccounts.' .
                ' Fill in the merchantaccount you want to use for this webshop.'
            )
        );

        // Test/Production mode
        $fields_form[0]['form']['input'][] = array(
            'type' => 'radio',
            'label' => $this->l('Test/Production Mode'),
            'name' => 'ADYEN_MODE',
            'values' => array(
                array(
                    'id' => 'prod',
                    'value' => 'live',
                    'label' => $this->l('Production')
                ),
                array(
                    'id' => 'test',
                    'value' => 'test',
                    'label' => $this->l('Test')
                )
            ),
            'required' => true
        );

        $fields_form[0]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Notification Username'),
            'name' => 'ADYEN_NOTI_USERNAME',
            'size' => 20,
            'required' => true,
            'hint' => $this->l(
                'Must correspond to the notification username in the Adyen Backoffice under' .
                ' Settings => Notifications'
            )
        );

        $notificationPassword = '';

        try {
            $notificationPassword = $this->crypto->decrypt(Configuration::get('ADYEN_NOTI_PASSWORD'));
        } catch (\Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error(
                'For configuration "ADYEN_NOTI_PASSWORD" an exception was thrown: ' . $e->getMessage()
            );
        } catch (\Adyen\PrestaShop\exception\MissingDataException $e) {
            $this->logger->debug(
                'The configuration "ADYEN_NOTI_PASSWORD" has no value set, please add the notification password!'
            );
        }

        $fields_form[0]['form']['input'][] = array(
            'type' => 'password',
            'label' => $this->l('Notification Password'),
            'name' => 'ADYEN_NOTI_PASSWORD',
            'desc' => $notificationPassword ? '' : $this->l('Please fill your notification password'),
            'class' => $notificationPassword ? 'adyen-input-green' : '',
            'size' => 20,
            'required' => false,
            'hint' => $this->l(
                'Must correspond to the notification password in the Adyen Backoffice under' .
                ' Settings => Notifications'
            )
        );

        $notificationHmacKey = '';

        try {
            $notificationHmacKey = $this->crypto->decrypt(Configuration::get('ADYEN_NOTI_HMAC'));
        } catch (\Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error('For configuration "ADYEN_NOTI_HMAC" an exception was thrown: ' . $e->getMessage());
        } catch (\Adyen\PrestaShop\exception\MissingDataException $e) {
            $this->logger->debug('The configuration "ADYEN_NOTI_HMAC" has no value set, please add the HMAC key!');
        }

        $fields_form[0]['form']['input'][] = array(
            'type' => 'password',
            'label' => $this->l('HMAC key for notifications'),
            'name' => 'ADYEN_NOTI_HMAC',
            'desc' => $notificationHmacKey ? '' : $this->l('Please fill your notification HMAC key'),
            'class' => $notificationHmacKey ? 'adyen-input-green' : '',
            'size' => 20,
            'required' => false,
            'hint' => $this->l(
                'Must correspond to the notification HMAC Key in the Adyen Backoffice under' .
                ' Settings => Notifications => Additional Settings => HMAC Key (HEX Encoded)'
            )
        );

        $cronjobToken = '';

        try {
            $cronjobToken = $this->crypto->decrypt(Configuration::get('ADYEN_CRONJOB_TOKEN'));
        } catch (\Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error(
                'For configuration "ADYEN_CRONJOB_TOKEN" an exception was thrown: ' . $e->getMessage()
            );
        } catch (\Adyen\PrestaShop\exception\MissingDataException $e) {
            $this->logger->debug(
                'The configuration "ADYEN_CRONJOB_TOKEN" has no value set, please add a secure token!'
            );
        }

        $fields_form[0]['form']['input'][] = array(
            'type' => 'text',
            'desc' => $cronjobToken ?
                $this->l('Your adyen cron job processor\'s url includes this secure token. Your URL looks like: ') .
                sprintf(
                    "%s/%s/index.php?fc=module&controller=AdminAdyenOfficialPrestashopCron&token=%s",
                    Tools::getShopDomainSsl(),
                    basename(_PS_ADMIN_DIR_),
                    $cronjobToken
                ) :
                $this->l('Please fill your cron job token'),
            'class' => $cronjobToken ? 'adyen-input-green' : '',
            'label' => $this->l('Secure token for cron job'),
            'name' => 'ADYEN_CRONJOB_TOKEN',
            'size' => 20,
            'required' => false
        );

        $apiKeyTest = '';

        try {
            $apiKeyTest = $this->crypto->decrypt(Configuration::get('ADYEN_APIKEY_TEST'));
        } catch (\Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error(
                'For configuration "ADYEN_APIKEY_TEST" an exception was thrown: ' . $e->getMessage()
            );
        } catch (\Adyen\PrestaShop\exception\MissingDataException $e) {
            $this->logger->debug('The configuration "ADYEN_APIKEY_TEST" has no value set.');
        }

        $apiKeyTestLastDigits = Tools::substr($apiKeyTest, -4);

        $fields_form[0]['form']['input'][] = array(
            'type' => 'password',
            'label' => $this->l('API key for Test'),
            'name' => 'ADYEN_APIKEY_TEST',
            'desc' => $apiKeyTestLastDigits ? $this->l('Saved key ends in: ') . $apiKeyTestLastDigits : $this->l(
                'Please fill your API key for Test'
            ),
            'class' => $apiKeyTestLastDigits ? 'adyen-input-green' : '',
            'size' => 20,
            'required' => false,
            'hint' => $this->l(
                'If you don\'t know your Api-Key, log in to your Test Customer Area. Navigate to' .
                ' Settings > Users > System, and click on your webservice user, normally this will be' .
                ' ws@Company.YourCompanyAccount. Under Checkout token is your API Key.'
            )
        );

        $apiKeyLive = '';

        try {
            $apiKeyLive = $this->crypto->decrypt(Configuration::get('ADYEN_APIKEY_LIVE'));
        } catch (\Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error(
                'For configuration "ADYEN_APIKEY_LIVE" an exception was thrown: ' . $e->getMessage()
            );
        } catch (\Adyen\PrestaShop\exception\MissingDataException $e) {
            $this->logger->debug('The configuration "ADYEN_APIKEY_LIVE" has no value set.');
        }

        $apiKeyLiveLastDigits = Tools::substr($apiKeyLive, -4);

        $fields_form[0]['form']['input'][] = array(
            'type' => 'password',
            'label' => $this->l('API key for Live'),
            'name' => 'ADYEN_APIKEY_LIVE',
            'desc' => $apiKeyLiveLastDigits ? $this->l('Saved key ends in: ') . $apiKeyLiveLastDigits : $this->l(
                'Please fill your API key for Live'
            ),
            'class' => $apiKeyLiveLastDigits ? 'adyen-input-green' : '',
            'size' => 20,
            'required' => false,
            'hint' => $this->l(
                'If you don\'t know your Api-Key, log in to your Live Customer Area. Navigate to' .
                ' Settings > Users > System, and click on your webservice user, normally this will be' .
                ' ws@Company.YourCompanyAccount. Under Checkout token is your API Key.'
            )
        );

        // Client key input test
        $fields_form[0]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Client key test'),
            'name' => 'ADYEN_CLIENTKEY_TEST',
            'size' => 50,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                'If you don\'t know your client key, log in to your Test Customer Area. Navigate to' .
                ' Settings > Users > System, and click on your webservice user, normally this will be' .
                ' ws@Company.YourCompanyAccount. Under Client Key is your Client Key.'
            )
        );

        // Client key input live
        $fields_form[0]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Client key live'),
            'name' => 'ADYEN_CLIENTKEY_LIVE',
            'size' => 50,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                'If you don\'t know your client key, log in to your Live Customer Area. Navigate to' .
                ' Settings > Users > System, and click on your webservice user, normally this will be' .
                ' ws@Company.YourCompanyAccount. Under Client Key is your Client Key.'
            )
        );

        // Live endpoint prefix
        $fields_form[0]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Live endpoint prefix'),
            'name' => 'ADYEN_LIVE_ENDPOINT_URL_PREFIX',
            'size' => 20,
            'required' => false,
            'hint' => $this->l(
                'The URL prefix [random]-[company name] from your Adyen live > Account > API URLs.'
            )
        );

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Payment method configurations'),
                'image' => '../img/admin/edit.gif'
            ),
            'input' => array(),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        // Apple pay merchant name input
        $fields_form[1]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Apple Pay merchant name'),
            'name' => 'ADYEN_APPLE_PAY_MERCHANT_NAME',
            'size' => 50,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                ''
            )
        );

        // Apple pay merchant identifier input
        $fields_form[1]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Apple Pay merchant identifier'),
            'name' => 'ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER',
            'size' => 50,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                ''
            )
        );

        // Google pay gateway merchant id
        $fields_form[1]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Google Pay gateway merchant ID'),
            'name' => 'ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID',
            'size' => 50,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                ''
            )
        );

        // Google pay merchant identifier input
        $fields_form[1]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Google Pay merchant identifier'),
            'name' => 'ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER',
            'size' => 50,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                ''
            )
        );

        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->l('Developer settings'),
                'image' => '../img/admin/edit.gif'
            ),
            'input' => array(),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        // Integrator name input
        $fields_form[2]['form']['input'][] = array(
            'type' => 'text',
            'label' => $this->l('Integrator Name'),
            'name' => 'ADYEN_INTEGRATOR_NAME',
            'size' => 20,
            'required' => false,
            'lang' => false,
            'hint' => $this->l(
                'Name of the integrator used. Leave blank if no integrator was utilised.'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => sprintf(
                    "%s&configure=%s&save%s&token=%s",
                    AdminController::$currentIndex,
                    $this->name,
                    $this->name,
                    Tools::getAdminTokenLite('AdminModules')
                )
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        if (Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $merchant_account = (string)Tools::getValue('ADYEN_MERCHANT_ACCOUNT');
            $integrator_name = (string)Tools::getValue('ADYEN_INTEGRATOR_NAME');
            $mode = (string)Tools::getValue('ADYEN_MODE');
            $notification_username = (string)Tools::getValue('ADYEN_NOTI_USERNAME');
            $cron_job_token = Tools::getValue('ADYEN_CRONJOB_TOKEN');
            $client_key_test = Tools::getValue('ADYEN_CLIENTKEY_TEST');
            $client_key_live = Tools::getValue('ADYEN_CLIENTKEY_LIVE');
            $live_endpoint_url_prefix = (string)Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $apple_pay_merchant_name = Tools::getValue('ADYEN_APPLE_PAY_MERCHANT_NAME');
            $apple_pay_merchant_identifier = Tools::getValue('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER');
            $google_pay_gateway_merchant_id = Tools::getValue('ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID');
            $google_pay_merchant_identifier = Tools::getValue('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER');
        } else {
            $merchant_account = Configuration::get('ADYEN_MERCHANT_ACCOUNT');
            $integrator_name = Configuration::get('ADYEN_INTEGRATOR_NAME');
            $mode = Configuration::get('ADYEN_MODE');
            $notification_username = Configuration::get('ADYEN_NOTI_USERNAME');
            $cron_job_token = $cronjobToken;
            $client_key_test = Configuration::get('ADYEN_CLIENTKEY_TEST');
            $client_key_live = Configuration::get('ADYEN_CLIENTKEY_LIVE');
            $live_endpoint_url_prefix = Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');

            $apple_pay_merchant_name = Configuration::get('ADYEN_APPLE_PAY_MERCHANT_NAME');
            $apple_pay_merchant_identifier = Configuration::get('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER');
            $google_pay_gateway_merchant_id = Configuration::get('ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID');
            $google_pay_merchant_identifier = Configuration::get('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER');
        }

        // Load current value
        $helper->fields_value['ADYEN_MERCHANT_ACCOUNT'] = $merchant_account;
        $helper->fields_value['ADYEN_INTEGRATOR_NAME'] = $integrator_name;
        $helper->fields_value['ADYEN_MODE'] = $mode;
        $helper->fields_value['ADYEN_NOTI_USERNAME'] = $notification_username;
        $helper->fields_value['ADYEN_CRONJOB_TOKEN'] = $cron_job_token;
        $helper->fields_value['ADYEN_CLIENTKEY_TEST'] = $client_key_test;
        $helper->fields_value['ADYEN_CLIENTKEY_LIVE'] = $client_key_live;
        $helper->fields_value['ADYEN_LIVE_ENDPOINT_URL_PREFIX'] = $live_endpoint_url_prefix;
        $helper->fields_value['ADYEN_APPLE_PAY_MERCHANT_NAME'] = $apple_pay_merchant_name;
        $helper->fields_value['ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER'] = $apple_pay_merchant_identifier;
        $helper->fields_value['ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID'] = $google_pay_gateway_merchant_id;
        $helper->fields_value['ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER'] = $google_pay_merchant_identifier;

        return $helper->generateForm($fields_form);
    }

    /**
     * Hook payment options PrestaShop > 1.7
     *
     * @return array
     * @throws Exception
     */
    public function hookPaymentOptions()
    {
        $payment_options = array();

        //retrieve payment methods
        $paymentMethods = $this->helper_data->fetchPaymentMethods($this->context->cart, $this->context->language);

        if (!$this->context->customer->is_guest && !empty($paymentMethods['storedPaymentMethods'])) {
            $storedPaymentMethods = $paymentMethods['storedPaymentMethods'];
            foreach ($storedPaymentMethods as $storedPaymentMethod) {
                if (!empty($storedPaymentMethod)) {
                    // Only show on the frontend the Ecommerce stored payment methods and not the ContAuth
                    if (!in_array('Ecommerce', $storedPaymentMethod['supportedShopperInteractions'])) {
                        continue;
                    }

                    $smartyVariables = array(
                        'storedPaymentApiId' => $storedPaymentMethod['id']
                    );

                    // Add checkout component default configuration parameters for smarty variables
                    $smartyVariables = array_merge($smartyVariables, $this->getCheckoutComponentInitData());
                    // Assign variables to frontend
                    $this->context->smarty->assign($smartyVariables);
                }

                $oneClickOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $oneClickOption->setCallToActionText(
                    $this->l(
                        'Pay by saved ' . $storedPaymentMethod['name'] . " ending: " . $storedPaymentMethod['lastFour']
                    )
                )
                    ->setForm(
                        $this->context->smarty->fetch(
                            _PS_MODULE_DIR_ . $this->name .
                            '/views/templates/front/stored-payment-method.tpl'
                        )
                    )
                    ->setLogo(
                        'https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/medium/' .
                        $storedPaymentMethod['brand'] . '.png'
                    )
                    ->setAction($this->context->link->getModuleLink($this->name, 'Payment', array(), true));

                $payment_options[] = $oneClickOption;
            }
        }

        if (!empty($paymentMethods['paymentMethods'])) {
            foreach ($paymentMethods['paymentMethods'] as $paymentMethod) {
                // Skip unsupported payment methods
                if ($this->isUnsupportedPaymentMethod($paymentMethod['type'])) {
                    continue;
                }

                $smartyVariables = array(
                    'paymentMethodType' => $paymentMethod['type'],
                    'paymentMethodName' => $paymentMethod['name']
                );

                // Add checkout component default configuration parameters for smarty variables
                $smartyVariables = array_merge($smartyVariables, $this->getCheckoutComponentInitData());

                // Assign variables to frontend
                $this->context->smarty->assign($smartyVariables);

                $logoName = $paymentMethod['type'];
                if ($logoName === 'scheme') {
                    $logoName = 'card';
                }

                $localPaymentMethod = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $localPaymentMethod->setCallToActionText($this->l('Pay by ' . $paymentMethod['name']))
                    ->setForm(
                        $this->context->smarty->fetch(
                            _PS_MODULE_DIR_ . $this->name .
                            '/views/templates/front/payment-method.tpl'
                        )
                    )
                    ->setLogo(
                        'https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/medium/' .
                        $logoName . '.png'
                    )
                    ->setAction(
                        $this->context->link->getModuleLink($this->name, 'Payment', array(), true)
                    );
                $payment_options[] = $localPaymentMethod;
            }
        }

        return $payment_options;
    }

    /**
     * Hook payment options PrestaShop <= 1.6
     *
     * @return string|null
     * @throws Exception
     */
    public function hookPayment()
    {
        if (!$this->active) {
            return null;
        }

        $this->context->controller->addCSS('modules/' . $this->name . '/views/css/adyen.css', 'all');

        $payments = "";
        $paymentMethods = $this->helper_data->fetchPaymentMethods($this->context->cart, $this->context->language);
        if (!$this->context->customer->is_guest && !empty($paymentMethods['storedPaymentMethods'])) {
            $payments .= $this->getOneClickPaymentMethods($paymentMethods);
        }

        if (!empty($paymentMethods['paymentMethods'])) {
            $payments .= $this->getLocalPaymentMethods($paymentMethods);
        }

        return $payments;
    }

    /**
     * @return array|null
     * @throws Exception
     */
    public function hookDisplayPaymentEU()
    {
        if (!$this->active) {
            return null;
        }

        $payment_options = array(
            'cta_text' => $this->l('Pay by Adyen'),
            'logo' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'),
            'form' => $this->hookPayment()
        );

        return $payment_options;
    }

    /**
     * Retrieve the necessary default configuration parameters for a checkout component
     *
     * @return array
     */
    private function getCheckoutComponentInitData()
    {
        $currencyData = Currency::getCurrency($this->context->cart->id_currency);

        $currencyIsoCode = '';
        if (!empty($currencyData['iso_code'])) {
            $currencyIsoCode = $currencyData['iso_code'];
        }

        $totalAmountInMinorUnits = $this->currencyUtil->sanitize(
            $this->context->cart->getOrderTotal(),
            $currencyIsoCode
        );

        // List of payment methods that needs to show the pay button from the component
        $paymentMethodsWithPayButtonFromComponent = json_encode(array('paywithgoogle', 'applepay', 'paypal'));

        // All payment method specific configuration
        $paymentMethodsConfigurations = json_encode(
            array(
                'applePayMerchantName' => Configuration::get('ADYEN_APPLE_PAY_MERCHANT_NAME'),
                'applePayMerchantIdentifier' => Configuration::get('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER'),
                'googlePayGatewayMerchantId' => Configuration::get('ADYEN_GOOGLE_PAY_GATEWAY_MERCHANT_ID'),
                'googlePayMerchantIdentifier' => Configuration::get('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER')
            )
        );

        return array(
            'locale' => $this->languageAdapter->getLocaleCode($this->context->language),
            'clientKey' => $this->configuration->clientKey,
            'environment' => Configuration::get('ADYEN_MODE'),
            'isUserLoggedIn' => !$this->context->customer->is_guest,
            'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
            'paymentsDetailsUrl' => $this->context->link->getModuleLink($this->name, 'PaymentsDetails', array(), true),
            'isPrestaShop16' => $this->versionChecker->isPrestaShop16() ? true : false,
            'currencyIsoCode' => $currencyIsoCode,
            'totalAmountInMinorUnits' => $totalAmountInMinorUnits,
            'paymentMethodsConfigurations' => $paymentMethodsConfigurations,
            'paymentMethodsWithPayButtonFromComponent' => $paymentMethodsWithPayButtonFromComponent
        );
    }

    /**
     * @param $params
     *
     * @return null
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return null;
        }

        if ($this->versionChecker->isPrestaShop16()) {
            $order = $params['objOrder'];
        } else {
            $order = $params['order'];
        }

        // Check if order object is not empty
        if (empty($order)) {
            $this->logger->debug('In hookPaymentReturn Order object is empty');
            return null;
        }

        // Retrieve payment response for order
        $paymentResponse = $this->adyenPaymentResponseModel->getPaymentResponseByCartId($order->id_cart);

        // Check if payment response is not empty and has action or additional data to show
        if (empty($paymentResponse) ||
            (empty($paymentResponse['action']) && empty($paymentResponse['additionalData']))
        ) {
            return null;
        }

        // Default parameters to frontend
        $smartyVariables = array(
            'paymentMethodsResponse' => '{}',
            'selectedInvoiceAddress' => '{}'
        );

        // checkout action if available
        if (!empty($paymentResponse['action'])) {
            $smartyVariables['action'] = json_encode($paymentResponse['action']);
        }

        // additional payment data if available
        if (!empty($paymentResponse['additionalData'])) {
            $smartyVariables['additionalData'] = json_encode($paymentResponse['additionalData']);
        }

        // Add checkout component default configuration parameters for smarty variables
        $smartyVariables = array_merge($smartyVariables, $this->getCheckoutComponentInitData());

        // Assign variables to frontend
        $this->context->smarty->assign($smartyVariables);

        return $this->display(__FILE__, '/views/templates/front/order-confirmation.tpl');
    }

    /**
     * @return null
     * @throws Exception
     */
    public function hookDisplayPaymentTop()
    {
        if (!$this->active) {
            return null;
        }

        $paymentMethods = $this->helper_data->fetchPaymentMethods($this->context->cart, $this->context->language);

        $selectedDeliveryAddressId = null;
        if ($this->context->cart->id_address_delivery) {
            $selectedDeliveryAddressId = $this->context->cart->id_address_delivery;
        }

        $selectedInvoiceAddressId = $selectedDeliveryAddressId;
        if ($this->context->cart->id_address_invoice) {
            $selectedInvoiceAddressId = $this->context->cart->id_address_invoice;
        }

        $selectedInvoiceAddressArray = array();

        /** @var AddressCore $selectedInvoiceAddress */
        $selectedInvoiceAddress = AddressCore::initialize($selectedInvoiceAddressId);
        if (\Validate::isLoadedObject($selectedInvoiceAddress)) {
            // Format the address in a way that frontend can use it
            $selectedInvoiceAddressArray = array(
                'city' => $selectedInvoiceAddress->city,
                'country' => $this->countryAdapter->getIsoById($selectedInvoiceAddress->id_country),
                'houseNumberOrName' => $selectedInvoiceAddress->address2,
                'postalCode' => $selectedInvoiceAddress->postcode,
                'street' => $selectedInvoiceAddress->address1
            );
        }

        $smartyVariables = array(
            'paymentMethodsResponse' => json_encode($paymentMethods),
            'selectedDeliveryAddressId' => $selectedDeliveryAddressId,
            'selectedInvoiceAddressId' => $selectedInvoiceAddressId,
            'selectedInvoiceAddress' => json_encode($selectedInvoiceAddressArray)
        );

        // Add checkout component default configuration parameters for smarty variables
        $smartyVariables = array_merge($smartyVariables, $this->getCheckoutComponentInitData());

        // Assign variables to frontend
        $this->context->smarty->assign($smartyVariables);

        return $this->display(__FILE__, '/views/templates/front/adyencheckout.tpl');
    }

    /**
     * Handles refunds
     *
     * For standard refunds, you need to:
     * 1. Enable Merchandise Returns in PrestaShop's admin panel, under the Customer Service area
     * 2. Generate a credit slip when refunding
     *
     * @param array $params
     *
     * @return bool|null
     */
    public function hookActionOrderSlipAdd(array $params)
    {
        if (!$this->active) {
            return null;
        }

        try {
            /** @var Adyen\PrestaShop\service\RefundService $refundService */
            $refundService = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
                'Adyen\PrestaShop\service\RefundService'
            );
        } catch (\PrestaShop\PrestaShop\Adapter\CoreException $e) {
            $this->addMessageToOrderForOrderSlipAndLogErrorMessage(
                'Error initializing Refund Service in actionOrderSlipAdd hook:'
                . PHP_EOL . $e->getMessage()
            );
            return false;
        }

        try {
            return $refundService->refund($params['order']);
        } catch (PrestaShopException $e) {
            $this->addMessageToOrderForOrderSlipAndLogErrorMessage(
                'Error while requesting a refund in actionOrderSlipAdd:'
                . PHP_EOL . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * @param $message
     * @param Order|null $order
     * @param OrderSlip|null $orderSlip
     */
    private function addMessageToOrderForOrderSlipAndLogErrorMessage(
        $message,
        Order $order = null,
        OrderSlip $orderSlip = null
    ) {
        if (isset($order) && isset($orderSlip)) {
            $this->addMessageToOrderForOrderSlip($message, $order, $orderSlip);
        }
        $this->logger->error($message);
    }

    /**
     * @param string $message
     * @param Order $order
     * @param OrderSlip $orderSlip
     * @return bool
     */
    private function addMessageToOrderForOrderSlip($message, Order $order, OrderSlip $orderSlip)
    {
        try {
            $customer = $order->getCustomer();
            if (empty($customer)) {
                throw new Adyen\PrestaShop\exception\GenericLoggedException(
                    "Customer with id: \"{$order->id_customer}\" cannot be found for" .
                    " order with id: \"{$order->id}\" while processing" .
                    " order slip with id: \"{$orderSlip->id}\"."
                );
            }
            $customerThread = $this->createCustomerThread($order, $orderSlip, $customer);
            $this->createCustomerMessage($message, $customerThread);
        } catch (Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param Order $order
     * @param OrderSlip $orderSlip
     * @param Customer $customer
     *
     * @return CustomerThread
     * @throws Adyen\PrestaShop\exception\GenericLoggedException
     */
    private function createCustomerThread(Order $order, OrderSlip $orderSlip, Customer $customer)
    {
        try {
            $customerThread = new CustomerThread(
                CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id)
            );
            if (empty($customerThread->id)) {
                $customerThread = new CustomerThread();
                $customerThread->id_contact = 0;
                $customerThread->id_customer = (int)$customer->id;
                $customerThread->id_shop = (int)$this->context->shop->id;
                $customerThread->id_order = (int)$order->id;
                $customerThread->id_lang = (int)$this->context->language->id;
                $customerThread->email = $customer->email;
                $customerThread->status = 'open';
                $customerThread->token = Tools::passwdGen(12);
                if (!$customerThread->add()) {
                    throw new Adyen\PrestaShop\exception\GenericLoggedException(
                        "Could not start a Customer Thread for Order Slip with id \"{$orderSlip->id}\"."
                    );
                }
            }
        } catch (PrestaShopDatabaseException $e) {
            throw new Adyen\PrestaShop\exception\GenericLoggedException(
                'Could not start a Customer Thread for Order Slip with id "' . $orderSlip->id .
                '". Reason:' . PHP_EOL . $e->getMessage()
            );
        } catch (PrestaShopException $e) {
            throw new Adyen\PrestaShop\exception\GenericLoggedException(
                "Could not start a Customer Thread for Order Slip with id \"" . $orderSlip->id .
                '". Reason:' . PHP_EOL . $e->getMessage()
            );
        }
        return $customerThread;
    }

    /**
     * @param $message
     * @param CustomerThread $customerThread
     *
     * @throws Adyen\PrestaShop\exception\GenericLoggedException
     */
    private function createCustomerMessage($message, CustomerThread $customerThread)
    {
        try {
            $customerMessage = new CustomerMessage();
            $customerMessage->id_customer_thread = $customerThread->id;
            $customerMessage->id_employee = $this->context->employee->id;
            $customerMessage->message = $message;
            $customerMessage->private = 1;

            if (!$customerMessage->add()) {
                throw new Adyen\PrestaShop\exception\GenericLoggedException(
                    'An error occurred while saving the message.'
                );
            }
        } catch (PrestaShopDatabaseException $e) {
            throw new Adyen\PrestaShop\exception\GenericLoggedException(
                'An error occurred while saving the message. Reason:' . PHP_EOL . $e->getMessage()
            );
        } catch (PrestaShopException $e) {
            throw new Adyen\PrestaShop\exception\GenericLoggedException(
                'An error occurred while saving the message. Reason:' . PHP_EOL . $e->getMessage()
            );
        }
    }

    /**
     * @param array $paymentMethods
     * @return string
     */
    private function getOneClickPaymentMethods(array $paymentMethods)
    {
        $payments = '';
        $storedPaymentMethods = $paymentMethods['storedPaymentMethods'];
        foreach ($storedPaymentMethods as $storedPayment) {
            if (!empty($storedPayment)) {
                // Only show on the frontend the Ecommerce stored payment methods and not the ContAuth
                if (!in_array('Ecommerce', $storedPayment['supportedShopperInteractions'])) {
                    continue;
                }

                $smartyVariables = array(
                    'storedPaymentApiId' => $storedPayment['id'],
                    'name' => $storedPayment['name'],
                    'number' => $storedPayment['lastFour']
                );

                // Add checkout component default configuration parameters for smarty variables
                $smartyVariables = array_merge($smartyVariables, $this->getCheckoutComponentInitData());

                // Assign variables to frontend
                $this->context->smarty->assign($smartyVariables);
            }
            $payments .= $this->display(__FILE__, '/views/templates/front/stored-payment-method.tpl');
        }
        return $payments;
    }

    /**
     * @param array $paymentMethods
     * @return string
     */
    private function getLocalPaymentMethods(array $paymentMethods)
    {
        $payments = '';
        foreach ($paymentMethods['paymentMethods'] as $paymentMethod) {
            // Skip unsupported payment methods
            if ($this->isUnsupportedPaymentMethod($paymentMethod['type'])) {
                continue;
            }

            $smartyVariables = array(
                'paymentMethodType' => $paymentMethod['type'],
                'paymentMethodName' => $paymentMethod['name']
            );

            // Add checkout component default configuration parameters for smarty variables
            $smartyVariables = array_merge($smartyVariables, $this->getCheckoutComponentInitData());

            // Assign variables to frontend
            $this->context->smarty->assign($smartyVariables);

            $payments .= $this->display(__FILE__, '/views/templates/front/payment-method.tpl');
        }
        return $payments;
    }

    /**
     * Returns true if payment method is unsupported
     *
     * @param $paymentMethodType
     * @return bool
     */
    private function isUnsupportedPaymentMethod($paymentMethodType)
    {
        $unsupportedPaymentMethods = array(
            'bcmc_mobile_QR',
            'wechatpay',
            'wechatpay_pos',
            'wechatpaySdk',
            'wechatpayQr'
        );

        if (in_array($paymentMethodType, $unsupportedPaymentMethods)) {
            return true;
        }

        return false;
    }

    /**
     * @param $params
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     * @noinspection PhpUnusedParameterInspection This method accepts a parameter and, even we don't use it,
     * it's better to make sure this is cataloged in the code base
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        // List of front controllers where we set the assets
        $frontControllers = array('order', 'order-confirmation', 'order-opc');
        $controller = $this->context->controller;

        if (in_array($controller->php_self, $frontControllers)) {
            $this->registerAdyenAssets($controller);
        }
    }

    /**
     * @param $controller
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function registerAdyenAssets($controller)
    {
        /** @var \Adyen\PrestaShop\service\adapter\classes\Controller $controllerAdapter */
        $controllerAdapter = $this->getService('Adyen\PrestaShop\service\adapter\classes\Controller');
        $controllerAdapter->setController($controller);

        // Needs to be rendered for each controller
        // Register Checkout component js
        $controllerAdapter->registerJavascript(
            'adyen-checkout-component', // Unique ID
            'modules/' . $this->name . '/views/js/bundle.js', // JS path
            array('position' => 'bottom', 'priority' => 150) // Arguments
        );

        $controllerAdapter->registerJavascript(
            'adyen-polyfill',
            'modules/' . $this->name . '/views/js/polyfill.js',
            array('position' => 'bottom', 'priority' => 140)
        );

        $controllerAdapter->registerJavascript(
            'adyen-component-renderer',
            'modules/' . $this->name . '/views/js/checkout-component-renderer.js',
            array('position' => 'bottom', 'priority' => 170)
        );

        $controllerAdapter->registerStylesheet(
            'adyen-adyencss',
            'modules/' . $this->name . '/views/css/adyen.css'
        );

        // Only for Order and Order one page checkout controller
        if ($controller->php_self == 'order' || $controller->php_self == 'order-opc') {
            if ($this->versionChecker->isPrestaShop16()) {
                $controller->addJqueryPlugin('fancybox');
            }
        }

        // Only for Order-confirmation controller
        if ($controller->php_self == 'order-confirmation') {
            $controllerAdapter->registerJavascript(
                'adyen-order-confirmation',
                'modules/' . $this->name . '/views/js/payment-components/order-confirmation.js',
                array('position' => 'bottom', 'priority' => 170)
            );
        }
    }

    /**
     * @param $serviceName
     * @return mixed|object
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function getService($serviceName)
    {
        return \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get($serviceName);
    }
}
