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

// init composer autoload
use Adyen\AdyenException;
use Adyen\PrestaShop\helper\Data as AdyenHelperData;
use Adyen\PrestaShop\model\Hashing;
use Adyen\PrestaShop\service\helper\DataFactory as AdyenHelperDataFactory;

require __DIR__ . '/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

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
     * @var AdyenHelperData
     */
    private $helper_data;

    /**
     * @var Hashing
     */
    private $hashing;

    /**
     * Adyen constructor.
     *
     * @throws AdyenException
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

        $adyenHelperFactory = new AdyenHelperDataFactory();
        $this->helper_data = $adyenHelperFactory->createAdyenHelperData(
            Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );

        $this->hashing = new Hashing();

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

    /*
	 * for installing the plugin
	 */
    public function install()
    {
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            $this->_errors[] = $this->l('Sorry, this module is not compatible with your version.');
            return false;
        }

        $this->updateCronJobToken();

        if ($this->helper_data->isPrestashop16()) {
            // Version 1.6 requires a different set of hooks
            if (
                parent::install()
                && $this->registerHook('displayPaymentTop')
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('payment')
                && $this->registerHook('displayPaymentEU')
                && $this->registerHook('paymentReturn')
                && $this->registerHook('displayHeader')
                && $this->registerHook('displayAdminOrder')
                && $this->registerHook('moduleRoutes')
                // the table for notifications from Adyen needs to be both in install and upgrade
                && $this->createAdyenNotificationTable()
                && $this->installTab()
            ) {
                return true;
            } else {
                Logger::addLog('Adyen module: installation failed!', 4);
                return false;
            }
        }

        // install hooks for version 1.7 or higher
        return parent::install()
            && $this->registerHook('displayPaymentTop')
            && $this->installTab()
            && $this->registerHook('header')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('adminOrder')
            && $this->registerHook('moduleRoutes')
            // the table for notifications from Adyen needs to be both in install and upgrade
            && $this->createAdyenNotificationTable();
    }

    /**
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
     * Uninstall script
     *
     * @return bool
     */
    public function uninstall()
    {
        // TODO: delete adyen configurations (api-key)
        $db = Db::getInstance();
        /** @noinspection SqlWithoutWhere SqlResolve */
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'adyen_notification`');

        return parent::uninstall() && $this->uninstallTab();
    }

    /**
     * @return bool true if tab is installed
     */
    public function installTab()
    {
        try {
            $tab = new Tab();// invisible tab
            $tab->id_parent = -1;
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
                    'required' => true,
                    'hint' => $this->l('Must correspond to the notification username in the Adyen Backoffice under Settings => Notifications')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Notification Password'),
                    'name' => 'ADYEN_NOTI_PASSWORD',
                    'size' => 20,
                    'required' => true,
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
                    'desc' => $this->l('Your adyen cron job processor\'s url includes this secure token . Your URL looks like: ' . _PS_BASE_URL_ . '/' . basename(_PS_ADMIN_DIR_) . '/index.php?fc=module&controller=AdminAdyenPrestashopCron&token=' . $this->helper_data->decrypt(Configuration::get('ADYEN_CRONJOB_TOKEN'))),
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
            $notification_HMAC = $this->hashing->hash(Tools::getValue('ADYEN_NOTI_HMAC', _COOKIE_KEY_));
            $cron_job_token = Tools::getValue('ADYEN_CRONJOB_TOKEN');
            $live_endpoint_url_prefix = (string)Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $api_key_test = $this->hashing->hash(Tools::getValue('ADYEN_APIKEY_TEST'), _COOKIE_KEY_);
            $api_key_live = $this->hashing->hash(Tools::getValue('ADYEN_APIKEY_LIVE'), _COOKIE_KEY_);
        } else {
            $merchant_account = Configuration::get('ADYEN_MERCHANT_ACCOUNT');
            $mode = Configuration::get('ADYEN_MODE');
            $notification_username = Configuration::get('ADYEN_NOTI_USERNAME');
            $notification_password = Configuration::get('ADYEN_NOTI_PASSWORD');
            $notification_HMAC = $this->hashing->hash(Configuration::get('ADYEN_NOTI_HMAC'), _COOKIE_KEY_);
            $cron_job_token = $this->helper_data->decrypt(Configuration::get('ADYEN_CRONJOB_TOKEN'));
            $live_endpoint_url_prefix = Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $api_key_test = $this->hashing->hash(Configuration::get('ADYEN_APIKEY_TEST'),
                _COOKIE_KEY_);;
            $api_key_live = $this->hashing->hash(Configuration::get('ADYEN_APIKEY_LIVE'),
                _COOKIE_KEY_);
        }

        // Load current value
        $helper->fields_value['ADYEN_MERCHANT_ACCOUNT'] = $merchant_account;
        $helper->fields_value['ADYEN_MODE'] = $mode;
        $helper->fields_value['ADYEN_NOTI_USERNAME'] = $notification_username;
        $helper->fields_value['ADYEN_NOTI_PASSWORD'] = $notification_password;
        $helper->fields_value['ADYEN_NOTI_HMAC'] = $notification_HMAC;
        $helper->fields_value['ADYEN_CRONJOB_TOKEN'] = $cron_job_token;
        $helper->fields_value['ADYEN_APIKEY_TEST'] = $api_key_test;
        $helper->fields_value['ADYEN_APIKEY_LIVE'] = $api_key_live;
        $helper->fields_value['ADYEN_LIVE_ENDPOINT_URL_PREFIX'] = $live_endpoint_url_prefix;

        return $helper->generateForm($fields_form);
    }

    /**
     * Hook for header Prestashop 1.6 & > 1.7
     */
    public function hookHeader()
    {
        if ($this->helper_data->isDemoMode()) {

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS(\Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_JS_TEST);
                $this->context->controller->addCSS(\Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_CSS_TEST);
                $this->context->controller->addJS($this->_path . 'views/js/threeds2-js-utils.js');
            } else {
                $this->context->controller->registerJavascript(
                    'component', // Unique ID
                    \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_JS_TEST, // JS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
                $this->context->controller->registerJavascript(
                    'threeDS2Utils', // Unique ID
                    $this->_path . 'views/js/threeds2-js-utils.js', // JS path
                    array('position' => 'bottom', 'priority' => 160) // Arguments
                );
                $this->context->controller->registerStylesheet(
                    'stylecheckout', // Unique ID
                    \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_CSS_TEST, // CSS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );

                $this->context->controller->registerStylesheet($this->name . '-adyencss',
                    $this->_path . '/css/adyen.css');

            }
        } else {

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS(\Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_JS_LIVE);
                $this->context->controller->addCSS(\Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_CSS_LIVE);
                $this->context->controller->addJS($this->_path . 'views/js/threeds2-js-utils.js');
            } else {
                $this->context->controller->registerJavascript(
                    'component', // Unique ID
                    \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_JS_LIVE, // JS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
                $this->context->controller->registerJavascript(
                    'threeDS2Utils', // Unique ID
                    $this->_path . 'views/js/threeds2-js-utils.js', // JS path
                    array('position' => 'bottom', 'priority' => 160) // Arguments
                );
                $this->context->controller->registerStylesheet(
                    'stylecheckout', // Unique ID
                    \Adyen\PrestaShop\service\Configuration::CHECKOUT_COMPONENT_CSS_LIVE, // CSS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
            }


        }

    }

    /**
     * Hook order confirmation PrestaShop 1.6 & > 1.7
     */
    public function hookOrderConfirmation()
    {
        if (!$this->active) {
            return;
        }
    }

    /**
     * Hook payment options PrestaShop > 1.7
     * @return array
     * @throws SmartyException
     */
    public function hookPaymentOptions()
    {
        $payment_options = array();
        if(!$this->context->customer->is_guest) {
            $amount = $this->context->cart->getOrderTotal();
            $currency = $this->context->currency->iso_code;
            $address = new Address($this->context->cart->id_address_invoice);
            $isoAddress = Country::getIsoById($address->id_country);
            $shopperReference = $this->context->cart->id_customer;
            $shopperLocale = $this->helper_data->getLocale($this->context);


            //retrieve payment methods
            $paymentMethods = $this->helper_data->fetchPaymentMethods($isoAddress, $amount, $currency, $shopperReference, $shopperLocale);

            if (!empty($paymentMethods['oneClickPaymentMethods'])) {
                $oneClickPaymentMethods = $paymentMethods['oneClickPaymentMethods'];
                foreach ($oneClickPaymentMethods as $storedCard) {
                    if (!empty($storedCard["storedDetails"]["card"]["expiryMonth"])) {

                        $this->context->smarty->assign(
                            array(
                                'locale' => $this->helper_data->getLocale($this->context),
                                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                                'environment' => Configuration::get('ADYEN_MODE'),
                                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment',
                                    array(),
                                    true),
                                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name,
                                    'ThreeDSProcess',
                                    array(), true),
                                'prestashop16' => false,
                                'oneClickPaymentMethod' => json_encode($storedCard),
                                'recurringDetailReference' => $storedCard['recurringDetailReference']
                            )
                        );
                    }
                    $oneclickOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                    $oneclickOption->setCallToActionText($this->l('Pay by saved ' . $storedCard['name'] . " ending: " . $storedCard['storedDetails']['card']['number']))
                        ->setForm($this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/front/oneclick.tpl'))
                        ->setLogo(\Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/' . $storedCard['type'] . '.png'))
                        ->setAction($this->context->link->getModuleLink($this->name, 'Payment', array(), true));

                    $payment_options[] = $oneclickOption;
                }
            }
        }


        $embeddedOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        $cc_img = 'cc_border.png';

        $this->context->smarty->assign(
            array(
                'locale' => $this->helper_data->getLocale($this->context),
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'prestashop16' => false,
                'loggedInUser' => !$this->context->customer->is_guest
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
            return;
        }

        $this->context->controller->addCSS($this->_path . 'css/adyen.css', 'all');

        $amount = $this->context->cart->getOrderTotal();
        $currency = $this->context->currency->iso_code;
        $address = new Address($this->context->cart->id_address_invoice);
        $isoAddress = Country::getIsoById($address->id_country);
        $shopperReference = $this->context->cart->id_customer;
        $shopperLocale = $this->helper_data->getLocale($this->context);

        $payments = "";
        $paymentMethods = $this->helper_data->fetchPaymentMethods($isoAddress, $amount, $currency, $shopperReference, $shopperLocale);
        if (!$this->context->customer->is_guest && !empty($paymentMethods['oneClickPaymentMethods'])) {
            $oneClickPaymentMethods = $paymentMethods['oneClickPaymentMethods'];
            foreach ($oneClickPaymentMethods as $storedCard) {
                if (!empty($storedCard["storedDetails"]["card"]["expiryMonth"])) {
                    $this->context->smarty->assign(
                        array(
                            'locale' => $this->helper_data->getLocale($this->context),
                            'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                            'environment' => Configuration::get('ADYEN_MODE'),
                            'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(),
                                true),
                            'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess',
                                array(), true),
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
        }

        $this->context->smarty->assign(
            array(
                'locale' => $this->helper_data->getLocale($this->context), // no locale in Prestashop1.6 only languageCode that is en-en but we need en_EN
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'prestashop16' => true,
                'loggedInUser' => !$this->context->customer->is_guest
            )
        );

        $payments .= $this->display(__FILE__, '/views/templates/front/payment.tpl');

        return $payments;
    }

    /**
     * @return array|void
     */
    public function hookDisplayPaymentEU()
    {
        if (!$this->active) {
            return;
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
        return;
    }

    /**
     *
     */
    public function hookDisplayPaymentTop()
    {
        $amount = $this->context->cart->getOrderTotal();
        $currency = $this->context->currency->iso_code;
        $address = new Address($this->context->cart->id_address_invoice);
        $isoAddress = Country::getIsoById($address->id_country);
        $shopperReference = $this->context->cart->id_customer;
        $shopperLocale = $this->helper_data->getLocale($this->context);

        //TODO: controller to prevent double paymentMethods call
        $paymentMethods = $this->helper_data->fetchPaymentMethods($isoAddress, $amount, $currency, $shopperReference, $shopperLocale);

        $this->context->smarty->assign(
            array(
                'locale' => $this->helper_data->getLocale($this->context),
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'paymentMethodsResponse' => json_encode($paymentMethods),
                'prestashop16' => $this->helper_data->isPrestashop16()
            )
        );

        return $this->display(__FILE__, '/views/templates/front/adyencheckout.tpl');
    }


}
