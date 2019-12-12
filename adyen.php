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
/** @noinspection PhpFullyQualifiedNameUsageInspection */
// PrestaShop good practices ask developers to check if PrestaShop is loaded
// before running any other PHP code, which breaks a PSR1 element.
// Also, the main class is not in a namespace, which breaks another element.
// phpcs:disable PSR1.Files.SideEffects,PSR1.Classes.ClassDeclaration

// init composer autoload
require __DIR__ . '/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

// this file cannot contain the `use` operator for PrestaShop 1.6

class Adyen extends PaymentModule
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
     * Adyen constructor.
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function __construct()
    {
        $this->name = 'adyen';
        $this->tab = 'payments_gateways';
        $this->version = \Adyen\PrestaShop\service\Configuration::VERSION;
        $this->author = 'Adyen';
        $this->bootstrap = true;
        $this->display = 'view';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
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

        // start for 1.6
        $this->is_eu_compatible = 1;
        // The need_instance flag indicates whether to load the module's class when displaying the "Modules" page in the back-office
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
                $this->createAdyenNotificationTable() &&
                $this->installTab() &&
                $this->updateCronJobToken() &&
                $this->createWaitingForPaymentOrderStatus()
            ) {
                return true;
            } else {
                $this->helper_data->adyenLogger()->logDebug('Adyen module: installation failed!', 4);
                return false;
            }
        }

        // Version 1.7 or higher
        if (parent::install() &&
            $this->registerHook('displayPaymentTop') &&
            $this->installTab() &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('orderConfirmation') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionOrderSlipAdd') &&
            $this->createAdyenNotificationTable() &&
            $this->updateCronJobToken() &&
            $this->createWaitingForPaymentOrderStatus()) {
            return true;
        } else {
            $this->helper_data->adyenLogger()->logDebug('Adyen module: installation failed!', 4);
            return false;
        }
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
            $this->helper_data->adyenLogger()->logDebug('Adyen module: reset failed!', 4);
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
            $token = $this->helper_data->encrypt(Tools::getShopDomainSsl() . time());
        }

        return Configuration::updateValue('ADYEN_CRONJOB_TOKEN', $this->helper_data->encrypt($token));
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
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT \'Updated At\',
            PRIMARY KEY (`entity_id`),
            KEY `ADYEN_NOTIFICATION_PSPREFERENCE` (`pspreference`),
            KEY `ADYEN_NOTIFICATION_EVENT_CODE` (`event_code`),
            KEY `ADYEN_NOTIFICATION_PSPREFERENCE_EVENT_CODE` (`pspreference`,`event_code`),
            KEY `ADYEN_NOTIFICATION_MERCHANT_REFERENCE_EVENT_CODE` (`merchant_reference`,`event_code`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT=\'Adyen Notifications\'';

        return $db->execute($query);
    }

    /**
     * Create a new order status: "waiting for payment"
     * @return mixed
     */
    public function createWaitingForPaymentOrderStatus()
    {
        if (!\Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT')) {
            $order_state = new \OrderState(); $order_state->name = [];
            foreach (\Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Waiting for payment';
            }

            $order_state->send_email = false;
            $order_state->invoice = false;
            $order_state->color = '#4169E1';
            $order_state->logable = true;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_ . '/img/os/13.gif';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            }

            return \Configuration::updateValue('ADYEN_OS_WAITING_FOR_PAYMENT', (int)$order_state->id);
        }

        return true;
    }

    /**
     *
     */
    private function removeAdyenDatabaseTables()
    {
        $db = Db::getInstance();
        /** @noinspection SqlWithoutWhere SqlResolve */
        return $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'adyen_notification`');
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
            'ADYEN_MODE',
            'ADYEN_NOTI_USERNAME',
            'ADYEN_NOTI_PASSWORD',
            'ADYEN_APIKEY_TEST',
            'ADYEN_APIKEY_LIVE',
            'ADYEN_NOTI_HMAC',
            'ADYEN_LIVE_ENDPOINT_URL_PREFIX',
            'ADYEN_CRONJOB_TOKEN'
        );

        $result = true;

        foreach ($adyenConfigurationNames as $adyenConfigurationName) {
            if (!Configuration::deleteByName($adyenConfigurationName)) {
                $this->helper_data->adyenLogger()->logDebug("Configuration couldn't be deleted by name: " . $adyenConfigurationName);
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
            $tab->class_name = 'AdminAdyenPrestashopCron';
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
            $id_tab = (int)Tab::getIdFromClassName('AdminAdyenPrestashopCron');
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
            $mode = (string)Tools::getValue('ADYEN_MODE');
            $notification_username = (string)Tools::getValue('ADYEN_NOTI_USERNAME');
            $notification_password = (string)Tools::getValue('ADYEN_NOTI_PASSWORD');
            $notification_hmac = (string)Tools::getValue('ADYEN_NOTI_HMAC');
            $cron_job_token = Tools::getValue('ADYEN_CRONJOB_TOKEN');
            $api_key_test = Tools::getValue('ADYEN_APIKEY_TEST');
            $api_key_live = Tools::getValue('ADYEN_APIKEY_LIVE');
            $live_endpoint_url_prefix = (string)Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');

            // validating the input
            if (empty($merchant_account) || !Validate::isGenericName($merchant_account)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Merchant Account'));
            }

            if (empty($notification_username) || !Validate::isGenericName($notification_username)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Notification Username'));
            }

            if (empty($notification_password) || !Validate::isGenericName($notification_password)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Notification Password'));
            }

            if ($output == null) {

                Configuration::updateValue('ADYEN_MERCHANT_ACCOUNT', $merchant_account);
                Configuration::updateValue('ADYEN_MODE', $mode);
                Configuration::updateValue('ADYEN_NOTI_USERNAME', $notification_username);
                Configuration::updateValue('ADYEN_NOTI_PASSWORD', $notification_password);
                Configuration::updateValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX', $live_endpoint_url_prefix);

                if (!empty($notification_hmac)) {
                    Configuration::updateValue('ADYEN_NOTI_HMAC', $notification_hmac);
                }
                if (!empty($cron_job_token)) {
                    Configuration::updateValue('ADYEN_CRONJOB_TOKEN', $this->helper_data->encrypt($cron_job_token));
                }
                if (!empty($api_key_test)) {
                    Configuration::updateValue('ADYEN_APIKEY_TEST', $this->helper_data->encrypt($api_key_test));
                }
                if (!empty($api_key_live)) {
                    Configuration::updateValue('ADYEN_APIKEY_LIVE', $this->helper_data->encrypt($api_key_live));
                }
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }

    /**
     * TODO: Implement displayGetStarted
     */
    public function displayGetStarted()
    {
    }

    /**
     * @return mixed
     */
    public function displayForm()
    {
        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('General Settings'),
                'image' => '../img/admin/edit.gif'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Merchant Account'),
                    'name' => 'ADYEN_MERCHANT_ACCOUNT',
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('In Adyen backoffice you have a company account with one or more merchantaccounts. Fill in the merchantaccount you want to use for this webshop.')
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Test/Production Mode'),
                    'name' => 'ADYEN_MODE',
                    'class' => 't',
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
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Notification Username'),
                    'name' => 'ADYEN_NOTI_USERNAME',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('Must correspond to the notification username in the Adyen Backoffice under Settings => Notifications')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Notification Password'),
                    'name' => 'ADYEN_NOTI_PASSWORD',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('Must correspond to the notification password in the Adyen Backoffice under Settings => Notifications')
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('HMAC key for notifications'),
                    'name' => 'ADYEN_NOTI_HMAC',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('Must correspond to the notification HMAC Key in the Adyen Backoffice under Settings => Notifications => Additional Settings => HMAC Key (HEX Encoded)')
                ),
                array(
                    'type' => 'text',
                    'desc' => $this->l('Your adyen cron job processor\'s url includes this secure token . Your URL looks like: ' . Tools::getShopDomainSsl() . '/' . basename(_PS_ADMIN_DIR_) . '/index.php?fc=module&controller=AdminAdyenPrestashopCron&token=' . $this->helper_data->decrypt(Configuration::get('ADYEN_CRONJOB_TOKEN'))),
                    'label' => $this->l('Secure token for cron job'),
                    'name' => 'ADYEN_CRONJOB_TOKEN',
                    'size' => 20,
                    'required' => false
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('API key for Test'),
                    'name' => 'ADYEN_APIKEY_TEST',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Api-Key, log in to your Test Customer Area. Navigate to Settings > Users > System, and click on your webservice user, normally this will be ws@Company.YourCompanyAccount. Under Checkout token is your API Key.')
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('API key for Live'),
                    'name' => 'ADYEN_APIKEY_LIVE',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Api-Key, log in to your Live Customer Area. Navigate to Settings > Users > System, and click on your webservice user, normally this will be ws@Company.YourCompanyAccount. Under Checkout token is your API Key.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Live endpoint prefix'),
                    'name' => 'ADYEN_LIVE_ENDPOINT_URL_PREFIX',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('The URL prefix [random]-[company name] from your Adyen live > Account > API URLs.')
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
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
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        if (Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $merchant_account = (string)Tools::getValue('ADYEN_MERCHANT_ACCOUNT');
            $mode = (string)Tools::getValue('ADYEN_MODE');
            $notification_username = (string)Tools::getValue('ADYEN_NOTI_USERNAME');
            $notification_password = (string)Tools::getValue('ADYEN_NOTI_PASSWORD');
            $cron_job_token = Tools::getValue('ADYEN_CRONJOB_TOKEN');
            $live_endpoint_url_prefix = (string)Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
        } else {
            $merchant_account = Configuration::get('ADYEN_MERCHANT_ACCOUNT');
            $mode = Configuration::get('ADYEN_MODE');
            $notification_username = Configuration::get('ADYEN_NOTI_USERNAME');
            $notification_password = Configuration::get('ADYEN_NOTI_PASSWORD');
            $cron_job_token = $this->helper_data->decrypt(Configuration::get('ADYEN_CRONJOB_TOKEN'));
            $live_endpoint_url_prefix = Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
        }

        // Load current value
        $helper->fields_value['ADYEN_MERCHANT_ACCOUNT'] = $merchant_account;
        $helper->fields_value['ADYEN_MODE'] = $mode;
        $helper->fields_value['ADYEN_NOTI_USERNAME'] = $notification_username;
        $helper->fields_value['ADYEN_NOTI_PASSWORD'] = $notification_password;
        $helper->fields_value['ADYEN_CRONJOB_TOKEN'] = $cron_job_token;
        $helper->fields_value['ADYEN_LIVE_ENDPOINT_URL_PREFIX'] = $live_endpoint_url_prefix;

        return $helper->generateForm($fields_form);
    }

    /**
     * Hook order confirmation PrestaShop 1.6 & > 1.7
     */
    public function hookOrderConfirmation()
    {
        if (!$this->active) {
            return null;
        }
    }

    /**
     * Hook payment options PrestaShop > 1.7
     *
     * @return array
     * @throws SmartyException
     * @throws Adyen\AdyenException
     */
    public function hookPaymentOptions()
    {
        $payment_options = array();

        //retrieve payment methods
        $paymentMethods = $this->helper_data->fetchPaymentMethods($this->context->cart, $this->context->language);

        if (!$this->context->customer->is_guest && !empty($paymentMethods['oneClickPaymentMethods'])) {
            $oneClickPaymentMethods = $paymentMethods['oneClickPaymentMethods'];
            foreach ($oneClickPaymentMethods as $storedCard) {
                if (!empty($storedCard["storedDetails"]["card"])) {
                    $this->context->smarty->assign(
                        array(
                            'locale' => $this->helper_data->getLocale($this->context->language),
                            'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                            'environment' => Configuration::get('ADYEN_MODE'),
                            'paymentProcessUrl' => $this->context->link->getModuleLink(
                                $this->name, 'Payment', array(), true
                            ),
                            'threeDSProcessUrl' => $this->context->link->getModuleLink(
                                $this->name, 'ThreeDSProcess', array(), true
                            ),
                            'prestashop16' => false,
                            'oneClickPaymentMethod' => json_encode($storedCard),
                            'recurringDetailReference' => $storedCard['recurringDetailReference']
                        )
                    );
                }
                $oneClickOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $oneClickOption->setCallToActionText(
                    $this->l(
                        'Pay by saved ' . $storedCard['name'] . " ending: " . $storedCard['storedDetails']['card']['number']
                    )
                )
                               ->setForm(
                                   $this->context->smarty->fetch(
                                       _PS_MODULE_DIR_ . $this->name . '/views/templates/front/oneclick.tpl'
                                   )
                               )
                               ->setLogo(
                                   \Media::getMediaPath(
                                       _PS_MODULE_DIR_ . $this->name . '/views/img/' . $storedCard['type'] . '.png'
                                   )
                               )
                               ->setAction($this->context->link->getModuleLink($this->name, 'Payment', array(), true));

                $payment_options[] = $oneClickOption;
            }
        }

        if (!empty($paymentMethods['paymentMethods'])) {
            foreach ($paymentMethods['paymentMethods'] as $paymentMethod) {
                $issuerList = array();

                if (!$this->isSimplePaymentMethod($paymentMethod)) {
                    continue;
                }

                // Skip unsupported payment methods
                if ($this->isUnsupportedPaymentMethod($paymentMethod['type'])) {
                    continue;
                }

                if (!empty($paymentMethod['details'])) {
                    foreach ($paymentMethod['details'] as $paymentMethodDetails) {
                        if (key_exists('key', $paymentMethodDetails) && $paymentMethodDetails['key'] == 'issuer') {
                            $issuerList = $paymentMethodDetails['items'];
                            break;
                        }
                    }
                }
                $this->context->smarty->assign(
                    array(
                        'locale' => $this->helper_data->getLocale($this->context->language),
                        'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                        'environment' => Configuration::get('ADYEN_MODE'),
                        'issuerList' => json_encode($issuerList),
                        'paymentMethodType' => $paymentMethod['type'],
                        'paymentMethodName' => $paymentMethod['name'],
                        'paymentProcessUrl' => $this->context->link->getModuleLink(
                            $this->name,
                            'Payment',
                            array(),
                            true
                        ),
                        'renderPayButton' => false,
                    )
                );
                $localPaymentMethod = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $localPaymentMethod->setCallToActionText($this->l('Pay by ' . $paymentMethod['name']))
                                   ->setForm(
                                       $this->context->smarty->fetch(
                                           _PS_MODULE_DIR_ . $this->name . '/views/templates/front/local-payment-method.tpl'
                                       )
                                   )
                                   ->setAction(
                                       $this->context->link->getModuleLink($this->name, 'Payment', array(), true)
                                   );
                $payment_options[] = $localPaymentMethod;
            }
        }

        $embeddedOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        $cc_img = 'cc_border.png';

        $this->context->smarty->assign(
            array(
                'locale' => $this->helper_data->getLocale($this->context->language),
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'prestashop16' => false,
                'loggedInUser' => (int)!$this->context->customer->is_guest
            )
        );

        $embeddedOption->setCallToActionText($this->l('Pay by card'))
            ->setForm($this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/front/payment.tpl'))
            ->setLogo(\Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/' . $cc_img))
            ->setAction($this->context->link->getModuleLink($this->name, 'Payment', array(), true));
        $payment_options[] = $embeddedOption;

        return $payment_options;
    }

    /**
     * Hook payment options PrestaShop <= 1.6
     * @return string|void
     */
    public function hookPayment()
    {
        if (!$this->active) {
            return null;
        }

        $this->context->controller->addCSS($this->_path . 'css/adyen.css', 'all');

        $payments = "";
        $paymentMethods = $this->helper_data->fetchPaymentMethods($this->context->cart, $this->context->language);
        if (!$this->context->customer->is_guest && !empty($paymentMethods['oneClickPaymentMethods'])) {
            $payments .= $this->getOneClickPaymentMethods($paymentMethods);
        }

        if (!empty($paymentMethods['paymentMethods'])) {
            $payments .= $this->getLocalPaymentMethods($paymentMethods);
        }

        $payments .= $this->getStandardPaymentMethod();

        return $payments;
    }

    /**
     * @return array|void
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
     *
     */
    public function hookPaymentReturn()
    {
        if (!$this->active) {
            return null;
        }
        return;
    }

    /**
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function hookDisplayPaymentTop()
    {
        if (!$this->active) {
            return null;
        }

        $paymentMethods = $this->helper_data->fetchPaymentMethods($this->context->cart, $this->context->language);

        $this->context->smarty->assign(
            array(
                'locale' => $this->helper_data->getLocale($this->context->language),
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'paymentMethodsResponse' => json_encode($paymentMethods),
                // string value is needed to be used in JavaScript code.
                'isPrestaShop16' => $this->versionChecker->isPrestaShop16() ? 'true' : 'false'
            )
        );

        return $this->display(__FILE__, '/views/templates/front/adyencheckout.tpl');
    }



    public function hookActionOrderSlipAdd(array $params)
    {
        if (!$this->active) {
            return null;
        }

        try {
            $modificationService = \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get(
                'Adyen\Service\ResourceModel\Modification'
            );
        } catch (Adyen\AdyenException $e) {
            $this->addMessageToOrderForOrderSlipAndLogErrorMessage(
                'Error initializing Adyen Modification Service in actionOrderSlipAdd hook:'
                . PHP_EOL . $e->getMessage()
            );
            return;
        } catch (\PrestaShop\PrestaShop\Adapter\CoreException $e) {
            $this->addMessageToOrderForOrderSlipAndLogErrorMessage(
                'Error initializing Adyen Modification Service in actionOrderSlipAdd hook:'
                . PHP_EOL . $e->getMessage()
            );
            return;
        }
        $refundService = new Adyen\PrestaShop\service\modification\Refund(
            $modificationService,
            Db::getInstance(),
            \Configuration::get('ADYEN_MERCHANT_ACCOUNT'),
            $this->helper_data->adyenLogger()
        );

        /** @var Order $order */
        $order = $params['order'];

        try {
            /** @var OrderSlip $orderSlip */
            $orderSlip = $order->getOrderSlipsCollection()->orderBy('date_upd', 'desc')->getFirst();
        } catch (PrestaShopException $e) {
            $this->addMessageToOrderForOrderSlipAndLogErrorMessage(
                'Error fetching order slips in actionOrderSlipAdd hook:' . PHP_EOL . $e->getMessage()
            );
            return;
        }

        $currency = Currency::getCurrency($order->id_currency);

        $refundService->request($orderSlip, $currency['iso_code']);
    }

    private function addMessageToOrderForOrderSlipAndLogErrorMessage(
        $message,
        Order $order = null,
        OrderSlip $orderSlip = null
    ) {
        if (isset($order) && isset($orderSlip)) {
            $this->addMessageToOrderForOrderSlip($message, $order, $orderSlip);
        }
        $this->helper_data->adyenLogger()->logError($message);
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
            $this->helper_data->adyenLogger()->logError($e->getMessage());
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
                    'An error occurred while saving the message.');
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
     *
     * @return string
     */
    private function getOneClickPaymentMethods(array $paymentMethods)
    {
        $payments = '';
        $oneClickPaymentMethods = $paymentMethods['oneClickPaymentMethods'];
        foreach ($oneClickPaymentMethods as $storedCard) {
            if (!empty($storedCard["storedDetails"]["card"])) {
                $this->context->smarty->assign(
                    array(
                        'locale' => $this->helper_data->getLocale($this->context->language),
                        'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                        'environment' => Configuration::get('ADYEN_MODE'),
                        'paymentProcessUrl' => $this->context->link->getModuleLink(
                            $this->name, 'Payment', array(),
                            true
                        ),
                        'threeDSProcessUrl' => $this->context->link->getModuleLink(
                            $this->name, 'ThreeDSProcess',
                            array(), true
                        ),
                        'prestashop16' => true,
                        'oneClickPaymentMethod' => json_encode($storedCard),
                        'recurringDetailReference' => $storedCard['recurringDetailReference'],
                        'name' => $storedCard['name'],
                        'number' => $storedCard['storedDetails']['card']['number']
                    )
                );
            }
            $payments .= $this->display(__FILE__, '/views/templates/front/oneclick.tpl');
        }
        return $payments;
    }

    /**
     * @param array $paymentMethods
     *
     * @return string
     */
    private function getLocalPaymentMethods(array $paymentMethods)
    {
        $payments = '';
        foreach ($paymentMethods['paymentMethods'] as $paymentMethod) {
            $issuerList = array();

            if (!$this->isSimplePaymentMethod($paymentMethod)) {
                continue;
            }

            // Skip unsupported payment methods
            if ($this->isUnsupportedPaymentMethod($paymentMethod['type'])) {
                continue;
            }

            if (isset($paymentMethod['details'])) {
                foreach ($paymentMethod['details'] as $paymentMethodDetails) {
                    if (key_exists('key', $paymentMethodDetails) && $paymentMethodDetails['key'] == 'issuer') {
                        $issuerList = $paymentMethodDetails['items'];
                        break;
                    }
                }
            }
            $this->context->smarty->assign(
                array(
                    'locale' => $this->helper_data->getLocale($this->context->language),
                    'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                    'environment' => Configuration::get('ADYEN_MODE'),
                    'issuerList' => json_encode($issuerList),
                    'paymentMethodType' => $paymentMethod['type'],
                    'paymentMethodName' => $paymentMethod['name'],
                    'paymentProcessUrl' => $this->context->link->getModuleLink(
                        $this->name,
                        'Payment',
                        array(),
                        true
                    ),
                    'renderPayButton' => true,
                )
            );
            $payments .= $this->display(__FILE__, '/views/templates/front/local-payment-method.tpl');
        }
        return $payments;
    }

    /**
     * @return string
     */
    private function getStandardPaymentMethod()
    {
        $payments = '';
        $this->context->smarty->assign(
            array(
                'locale' => $this->helper_data->getLocale($this->context->language),
                // no locale in PrestaShop1.6 only languageCode that is en-en but we need en_EN
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink(
                    $this->name, 'ThreeDSProcess', array(), true
                ),
                'prestashop16' => true,
                'loggedInUser' => !$this->context->customer->is_guest
            )
        );

        $payments .= $this->display(__FILE__, '/views/templates/front/payment.tpl');
        return $payments;
    }

    /**
     * @param array $paymentMethod
     *
     * @return bool
     */
    private function isSimplePaymentMethod($paymentMethod)
    {
        if (!empty($paymentMethod['details'])) {
            $details = $paymentMethod['details'];
        }
        return !empty($paymentMethod['type'])
            && $paymentMethod['type'] != 'scheme'
            && (
                empty($details) || (
                    is_array($details)
                    && count($details) == 1
                    && $details[0]['key'] == 'issuer'
                    && $details[0]['type'] == 'select'
                )
            );
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
            'wechatpayQr',
            'klarna',
            'klarna_b2b',
            'klarna_account',
            'klarna_paynow'
        );

        if (in_array($paymentMethodType, $unsupportedPaymentMethods)) {
            return true;
        }

        return false;
    }

    /**
     * @param $params
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $controller = $this->context->controller;
        if ($controller->php_self == 'order') {
            $this->registerAdyenJavascript($controller);
        }
    }

    /**
     * @param FrontController $controller
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function registerAdyenJavascript($controller)
    {
        /** @var \Adyen\PrestaShop\service\adapter\classes\Controller $controllerAdapter */
        $controllerAdapter = $this->getService('Adyen\PrestaShop\service\adapter\classes\Controller');
        $controllerAdapter->setController($controller);
        if ($this->helper_data->isDemoMode()) {
            $controllerAdapter->registerJavascript(
                'adyen-checkout-component', // Unique ID
                \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_JS_TEST, // JS path
                array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
            );
            $controllerAdapter->registerJavascript(
                'adyen-threeDS2Utils', // Unique ID
                $this->_path . 'views/js/threeds2-js-utils.js', // JS path
                array('position' => 'bottom', 'priority' => 160) // Arguments
            );
            $controllerAdapter->registerStylesheet(
                'adyen-stylecheckout', // Unique ID
                \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_CSS_TEST, // CSS path
                array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
            );

            $controllerAdapter->registerStylesheet(
                $this->name . '-adyencss',
                $this->_path . '/css/adyen.css'
            );
        } else {
            $controllerAdapter->registerJavascript(
                'adyen-component', // Unique ID
                \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_JS_LIVE, // JS path
                array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
            );
            $controllerAdapter->registerJavascript(
                'adyen-threeDS2Utils', // Unique ID
                $this->_path . 'views/js/threeds2-js-utils.js', // JS path
                array('position' => 'bottom', 'priority' => 160) // Arguments
            );
            $controllerAdapter->registerStylesheet(
                'adyen-stylecheckout', // Unique ID
                \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_CSS_LIVE, // CSS path
                array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
            );
        }

        $controllerAdapter->registerJavascript(
            'adyen-component-renderer',
            $this->_path . 'views/js/checkout-component-renderer.js',
            array('position' => 'bottom', 'priority' => 170)
        );
        $controllerAdapter->registerJavascript(
            'adyen-credit-card-validator',
            $this->_path . 'views/js/payment-components/credit-card.js',
            array('position' => 'bottom', 'priority' => 170)
        );
        $controllerAdapter->registerJavascript(
            'adyen-local-payment-method',
            $this->_path . 'views/js/payment-components/local-payment-method.js',
            array('position' => 'bottom', 'priority' => 170)
        );
        $controllerAdapter->registerJavascript(
            'adyen-one-click',
            $this->_path . 'views/js/payment-components/one-click.js',
            array('position' => 'bottom', 'priority' => 170)
        );

        if ($this->versionChecker->isPrestaShop16()) {
            $controller->addJqueryPlugin('fancybox');
        }
    }

    /**
     * @param $serviceName
     *
     * @return mixed|object
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function getService($serviceName)
    {
        return \Adyen\PrestaShop\service\adapter\classes\ServiceLocator::get($serviceName);
    }
}
