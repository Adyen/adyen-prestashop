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
require __DIR__ . '/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Adyen extends \PaymentModule
{
    const TEST = 'test';
    const LIVE = 'live';
    const CHECKOUT_COMPONENT_JS_TEST = 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/3.0.0/adyen.js';
    const CHECKOUT_COMPONENT_JS_LIVE = 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.0.0/adyen.js';
    const CHECKOUT_COMPONENT_CSS_TEST = 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/3.0.0/adyen.css';
    const CHECKOUT_COMPONENT_CSS_LIVE = 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.0.0/adyen.css';
    const VERSION = '1.0.1';
    const MODULE_NAME = 'adyen-prestashop';


    public function __construct()
    {
        $this->name = 'adyen';
        $this->tab = 'payments_gateways';
        $this->version = self::VERSION;
        $this->author = 'Adyen';
        $this->bootstrap = true;
        $this->display = 'view';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->currencies = true;

        $adyenHelperFactory = new \Adyen\PrestaShop\service\Adyen\Helper\DataFactory();
        $this->helper_data = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );

        $this->hashing = new \Adyen\PrestaShop\model\Hashing();

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

        if ($this->helper_data->isPrestashop16()) {
            // Version 1.6 requires a different set of hooks
            if (
                parent::install()
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('payment')
                && $this->registerHook('displayPaymentEU')
                && $this->registerHook('paymentReturn')
                && $this->registerHook('displayHeader')
                && $this->registerHook('displayAdminOrder')
                && $this->registerHook('moduleRoutes')
                // the table for notifications from Adyen needs to be both in install and upgrade
                && $this->createAdyenNotificationTable()
            ) {
                return true;
            } else {
                \Logger::addLog('Adyen module: installation failed!', 4);
                return false;
            }
        }

        // install hooks for version 1.7 or higher
        return parent::install()
            && $this->registerHook('header')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('adminOrder')
            && $this->registerHook('moduleRoutes')
            // the table for notifications from Adyen needs to be both in install and upgrade
            && $this->createAdyenNotificationTable();
    }

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

        $db->execute($query);

        return true;
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
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'adyen_notification`');
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'adyen_notification`');

        return parent::uninstall();
    }


    /**
     * shows the configuration page in the back-end
     */

    public function getContent()
    {
        $output = null;

        if (\Tools::isSubmit('submit' . $this->name)) {
            // get post values
            $merchant_account = (string)\Tools::getValue('ADYEN_MERCHANT_ACCOUNT');
            $mode = (string)\Tools::getValue('ADYEN_MODE');
            $notification_username = (string)\Tools::getValue('ADYEN_NOTI_USERNAME');
            $notification_password = (string)\Tools::getValue('ADYEN_NOTI_PASSWORD');
            $notification_hmac = (string)\Tools::getValue('ADYEN_NOTI_HMAC');
            $api_key_test = \Tools::getValue('ADYEN_APIKEY_TEST');
            $api_key_live = \Tools::getValue('ADYEN_APIKEY_LIVE');
            $live_endpoint_url_prefix = (string)\Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');


            // validating the input
            if (!$merchant_account || empty($merchant_account) || !\Validate::isGenericName($merchant_account)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Merchant Account'));
            }

            if (!$notification_username || empty($notification_username) || !\Validate::isGenericName($notification_username)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Notification Username'));
            }

            if (!$notification_password || empty($notification_password) || !\Validate::isGenericName($notification_password)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Notification Password'));
            }

            if (!$notification_hmac || empty($notification_hmac) || !\Validate::isGenericName($notification_hmac)) {
                $output .= $this->displayError($this->l('Invalid Configuration value for Notification HMAC Key'));
            }


            if ($output == null) {

                \Configuration::updateValue('ADYEN_MERCHANT_ACCOUNT', $merchant_account);
                \Configuration::updateValue('ADYEN_MODE', $mode);
                \Configuration::updateValue('ADYEN_NOTI_USERNAME', $notification_username);
                \Configuration::updateValue('ADYEN_NOTI_PASSWORD', $notification_password);
                \Configuration::updateValue('ADYEN_NOTI_HMAC', $notification_hmac);
                \Configuration::updateValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX', $live_endpoint_url_prefix);
                if (!empty($api_key_test)) {
                    \Configuration::updateValue('ADYEN_APIKEY_TEST', $this->helper_data->encrypt($api_key_test));
                }
                if (!empty($api_key_live)) {
                    \Configuration::updateValue('ADYEN_APIKEY_LIVE', $this->helper_data->encrypt($api_key_live));
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
        $default_lang = (int)\Configuration::get('PS_LANG_DEFAULT');

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
                    'type' => 'text',
                    'label' => $this->l('HMAC key for notifications'),
                    'name' => 'ADYEN_NOTI_HMAC',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('Must correspond to the notification HMAC Key in the Adyen Backoffice under Settings => Notifications => Additional Settings => HMAC Key (HEX Encoded)')
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

        $helper = new \HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = \AdminController::$currentIndex . '&configure=' . $this->name;

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
                'href' => \AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . \Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'href' => \AdminController::$currentIndex . '&token=' . \Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        if (\Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $merchant_account = (string)\Tools::getValue('ADYEN_MERCHANT_ACCOUNT');
            $mode = (string)\Tools::getValue('ADYEN_MODE');
            $notification_username = (string)\Tools::getValue('ADYEN_NOTI_USERNAME');
            $notification_password = (string)\Tools::getValue('ADYEN_NOTI_PASSWORD');
            $notification_HMAC = (string)\Tools::getValue('ADYEN_NOTI_HMAC');
            $live_endpoint_url_prefix = (string)\Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $api_key_test = $this->hashing->hash(\Tools::getValue('ADYEN_APIKEY_TEST'), _COOKIE_KEY_);
            $api_key_live = $this->hashing->hash(\Tools::getValue('ADYEN_APIKEY_LIVE'), _COOKIE_KEY_);
        } else {
            $merchant_account = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');
            $mode = \Configuration::get('ADYEN_MODE');
            $notification_username = \Configuration::get('ADYEN_NOTI_USERNAME');
            $notification_password = \Configuration::get('ADYEN_NOTI_PASSWORD');
            $notification_HMAC = \Configuration::get('ADYEN_NOTI_HMAC');
            $live_endpoint_url_prefix = \Configuration::get('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $api_key_test = $this->hashing->hash(\Configuration::get('ADYEN_APIKEY_TEST'),
                _COOKIE_KEY_);;
            $api_key_live = $this->hashing->hash(\Configuration::get('ADYEN_APIKEY_LIVE'),
                _COOKIE_KEY_);
        }

        // Load current value
        $helper->fields_value['ADYEN_MERCHANT_ACCOUNT'] = $merchant_account;
        $helper->fields_value['ADYEN_MODE'] = $mode;
        $helper->fields_value['ADYEN_NOTI_USERNAME'] = $notification_username;
        $helper->fields_value['ADYEN_NOTI_PASSWORD'] = $notification_password;
        $helper->fields_value['ADYEN_NOTI_HMAC'] = $notification_HMAC;
        $helper->fields_value['ADYEN_APIKEY_TEST'] = $api_key_test;
        $helper->fields_value['ADYEN_APIKEY_LIVE'] = $api_key_live;
        $helper->fields_value['ADYEN_LIVE_ENDPOINT_URL_PREFIX'] = $live_endpoint_url_prefix;

        return $helper->generateForm($fields_form);
    }

    /**
     * TODO: implement updateOptionsConfiguration
     * @Method: updateConfiguration
     * @description: submitOptionsconfiguration update values
     *
     */
    private function updateOptionsConfiguration()
    {
        if (Tools::isSubmit('submitOptionsconfiguration')) {
        }

    }

    /**
     * Hook for header Prestashop 1.6 & > 1.7
     */
    public function hookHeader()
    {
        if ($this->helper_data->isDemoMode()) {

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS(self::CHECKOUT_COMPONENT_JS_TEST);
                $this->context->controller->addCSS(self::CHECKOUT_COMPONENT_CSS_TEST);
                $this->context->controller->addJS($this->_path . 'views/js/threeds2-js-utils.js');
            } else {
                $this->context->controller->registerJavascript(
                    'component', // Unique ID
                    self::CHECKOUT_COMPONENT_JS_TEST, // JS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
                $this->context->controller->registerJavascript(
                    'threeDS2Utils', // Unique ID
                    $this->_path . 'views/js/threeds2-js-utils.js', // JS path
                    array('position' => 'bottom', 'priority' => 160) // Arguments
                );
                $this->context->controller->registerStylesheet(
                    'stylecheckout', // Unique ID
                    self::CHECKOUT_COMPONENT_CSS_TEST, // CSS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );

                $this->context->controller->registerStylesheet($this->name.'-adyencss',$this->_path .'/css/adyen.css');

            }
        } else {

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS(self::CHECKOUT_COMPONENT_JS_LIVE);
                $this->context->controller->addCSS(self::CHECKOUT_COMPONENT_CSS_LIVE);
                $this->context->controller->addJS($this->_path . 'views/js/threeds2-js-utils.js');
            } else {
                $this->context->controller->registerJavascript(
                    'component', // Unique ID
                    self::CHECKOUT_COMPONENT_JS_LIVE, // JS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
                $this->context->controller->registerJavascript(
                    'threeDS2Utils', // Unique ID
                    $this->_path . 'views/js/threeds2-js-utils.js', // JS path
                    array('position' => 'bottom', 'priority' => 160) // Arguments
                );
                $this->context->controller->registerStylesheet(
                    'stylecheckout', // Unique ID
                    self::CHECKOUT_COMPONENT_CSS_LIVE, // CSS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
            }


        }

    }

    /**
     * Hook order confirmation Prestashop 1.6 & > 1.7
     * @param $params
     */
    public function hookOrderConfirmation($params)
    {
        if (!$this->active)
            return;
    }


    /**
     * Hook payment options Prestashop > 1.7
     * @param $params
     * @return array
     * @throws \Adyen\AdyenException
     */
    public function hookPaymentOptions($params)
    {
        $payment_options = array();
        $embeddedOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        $cc_img = 'cc_border.png';

        $this->context->smarty->assign(
            array(
                'locale' => $this->context->language->locale,
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => \Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'prestashop16' => false
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
     * Hook payment options Prestashop <= 1.6
     * @param $params
     * @throws \Adyen\AdyenException
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        $this->context->controller->addCSS($this->_path.'css/adyen.css', 'all');

        $this->context->smarty->assign(
            array(
                'locale' => $this->context->language->iso_code, // no locale in Prestashop1.6 only languageCode that is en-en but we need en_EN
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => \Configuration::get('ADYEN_MODE'),
                'paymentProcessUrl' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'threeDSProcessUrl' => $this->context->link->getModuleLink($this->name, 'ThreeDSProcess', array(), true),
                'prestashop16' => true
            )
        );


        return $this->display(__FILE__, '/views/templates/front/payment.tpl');
    }

    /**
     * @param $params
     * @return array|void
     */
    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return;
        }

        $payment_options = array(
            'cta_text' => $this->l('Pay by Adyen'),
            'logo' => \Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'),
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

}
