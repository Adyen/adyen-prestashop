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
 * Adyen Prestashop Extension
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


    public function __construct()
    {
        $this->name = 'adyen';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->author = 'Adyen';
        $this->bootstrap = true;
        $this->display = 'view';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->currencies = true;
        $this->helper_data = new \Adyen\PrestaShop\helper\Data();
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
            // Version is 1.6
            if (parent::install() == false || !$this->registerHook('displayBackOfficeHeader') || !$this->registerHook('payment') || !$this->registerHook('displayPaymentEU') || !$this->registerHook('paymentReturn') || !$this->registerHook('displayHeader') || !$this->registerHook('displayAdminOrder')) {
                \Logger::addLog('Adyen module: installation failed!', 4);
                return false;
            }
            return true;
        }

        // version is 1.7 or higher
        return parent::install()
            && $this->registerHook('header')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('adminOrder');
    }

    /**
     * Uninstall script
     *
     * @return bool
     */
    public function uninstall()
    {
        // TODO: delete adyen configurations (api-key)
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
            $api_key_test = $this->helper_data->encrypt(\Tools::getValue('ADYEN_APIKEY_TEST'));
            $api_key_live = $this->helper_data->encrypt(\Tools::getValue('ADYEN_APIKEY_LIVE'));
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


            if ($output == null) {

                \Configuration::updateValue('ADYEN_MERCHANT_ACCOUNT', $merchant_account);
                \Configuration::updateValue('ADYEN_MODE', $mode);
                \Configuration::updateValue('ADYEN_NOTI_USERNAME', $notification_username);
                \Configuration::updateValue('ADYEN_NOTI_PASSWORD', $notification_password);
                \Configuration::updateValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX', $live_endpoint_url_prefix);
                if (!empty($api_key_test)) {
                    \Configuration::updateValue('ADYEN_APIKEY_TEST', $api_key_test);
                }
                if (!empty($api_key_live)) {
                    \Configuration::updateValue('ADYEN_APIKEY_LIVE', $api_key_live);
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
            $live_endpoint_url_prefix = (string)\Tools::getValue('ADYEN_LIVE_ENDPOINT_URL_PREFIX');
            $api_key_test = $this->hashing->hash(\Tools::getValue('ADYEN_APIKEY_TEST'), _COOKIE_KEY_);
            $api_key_live = $this->hashing->hash(\Tools::getValue('ADYEN_APIKEY_LIVE'), _COOKIE_KEY_);
        } else {
            $merchant_account = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');
            $mode = \Configuration::get('ADYEN_MODE');
            $notification_username = \Configuration::get('ADYEN_NOTI_USERNAME');
            $notification_password = \Configuration::get('ADYEN_NOTI_PASSWORD');
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
            } else {
                $this->context->controller->registerJavascript(
                    'component', // Unique ID
                    self::CHECKOUT_COMPONENT_JS_TEST, // JS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
                $this->context->controller->registerStylesheet(
                    'stylecheckout', // Unique ID
                    self::CHECKOUT_COMPONENT_CSS_TEST, // CSS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
                );
            }
        } else {

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS(self::CHECKOUT_COMPONENT_JS_LIVE);
                $this->context->controller->addCSS(self::CHECKOUT_COMPONENT_CSS_LIVE);
            } else {
                $this->context->controller->registerJavascript(
                    'component', // Unique ID
                    self::CHECKOUT_COMPONENT_JS_LIVE, // JS path
                    array('server' => 'remote', 'position' => 'bottom', 'priority' => 150) // Arguments
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
     */
    public function hookPaymentOptions($params)
    {
        $payment_options = array();
        $embeddedOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        $cc_img = 'cc_border.png';


        $this->context->smarty->assign(
            array(
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => \Configuration::get('ADYEN_MODE'),
                'action' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
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
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        $this->context->controller->addCSS($this->_path.'css/adyen.css', 'all');

        $this->context->smarty->assign(
            array(
                'originKey' => $this->helper_data->getOriginKeyForOrigin(),
                'environment' => \Configuration::get('ADYEN_MODE'),
                'action' => $this->context->link->getModuleLink($this->name, 'Payment', array(), true),
                'prestashop16' => true
            )
        );


        return $this->display(__FILE__, '/views/templates/front/payment.tpl');
    }

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
}