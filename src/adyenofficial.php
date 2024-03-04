<?php

/**
 * 2023 Adyen
 *
 * LICENSE PLACEHOLDER
 *
 * This source file is subject to the Apache License 2.0
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.apache.org/licenses/LICENSE-2.0.txt
 *
 * @author Adyen <support@adyen.com>
 * @copyright 2022 Adyen
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt  Apache License 2.0
 */


if (!defined('_PS_VERSION_')) {
    exit;
}

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Adyen module base class. This class represents main entry point for the plugin.
 * It is used for: installation, uninstallation, handling hook actions and handling configuration page.
 *
 * @property bool bootstrap
 * @property string name
 * @property string tab
 * @property string version
 * @property string author
 * @property int need_instance
 * @property array ps_versions_compliancy
 * @property string displayName
 * @property string description
 * @property string confirmUninstall
 */
class AdyenOfficial extends PaymentModule
{
    /**
     * Adyen module constructor.
     */
    public function __construct()
    {
        $this->name = 'adyenofficial';
        $this->tab = 'payments_gateways';
        $this->version = '5.1.10';

        $this->author = $this->l('Adyen');
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.5.0', 'max' => '8.1.3'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Adyen');
        $this->description = $this->l('Accept all payments offered by Adyen');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Gets module's context.
     *
     * @return Context|null
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Handle plugin installation.
     *
     * @return bool
     */
    public function install(): bool
    {
        try {
            $success = parent::install();
            $success && $this->getInstaller()->install();

            return $success;
        } catch (Throwable $e) {
            $this->_errors[] = $e->getMessage();
            \PrestaShopLogger::addLog(
                'Adyen plugin installation failed. Error: ' . $e->getMessage() . ' . Trace: ' . $e->getTraceAsString(),
                \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );

            return false;
        }
    }

    /**
     * Handle plugin uninstallation.
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        try {
            $success = parent::uninstall();
            $success && $this->getInstaller()->uninstall();

            return $success;
        } catch (Throwable $e) {
            $this->_errors[] = $e->getMessage();
            \PrestaShopLogger::addLog(
                'Adyen plugin uninstallation failed. Error: ' . $e->getMessage() . ' . Trace: ' . $e->getTraceAsString(
                ),
                \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );

            return false;
        }
    }

    /**
     * @param $force_all
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function enable($force_all = false): bool
    {
        $this->installOverrides();
        $success = parent::enable($force_all);
        $success && $this->getInstaller()->activateCustomOrderStates();

        return $success;
    }

    /**
     * @param $force_all
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function disable($force_all = false): bool
    {
        $this->uninstallOverrides();
        $success = parent::disable($force_all);
        $success && $this->getInstaller()->deactivateCustomOrderStates();

        return $success;
    }

    /**
     * @return bool|int
     */
    public function installOverrides()
    {
        $this->uninstallOverrides();
        if (!$this->getInstaller()->shouldInstallOverrides()) {
            return true;
        }

        return parent::installOverrides();
    }

    /**
     * Shows the configuration page in the back office
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function getContent(): string
    {
        $isShopContext = \Shop::getContext() === \Shop::CONTEXT_SHOP;

        if (!$isShopContext) {
            $this->getContext()->controller->errors[] = $this->l('Please select the specific shop to configure.');

            return '';
        }

        if (!$this->isEnabledForShopContext()) {
            $this->getContext()->controller->errors[] = $this->l('Please enable the module.');

            return '';
        }

        \AdyenPayment\Classes\Bootstrap::init();

        /** @var \Adyen\Core\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup $wakeupService */
        $wakeupService = \Adyen\Core\Infrastructure\ServiceRegister::getService(
            \Adyen\Core\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup::CLASS_NAME
        );
        $wakeupService->wakeup();

        $this->loadStyles();
        $this->loadScripts();

        $this->context->smarty->assign(
            [
                'urls' => $this->getUrls(),
                'sidebar' => $this->getSidebarContent(),
                'translations' => $this->getTranslations()
            ]
        );

        return $this->display($this->_path, 'views/templates/index.tpl');
    }

    /**
     * @param array $params
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderGridDefinitionModifier(array $params): void
    {
        $definition = $params['definition'];

        /** @var \PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection */
        $columns = $definition->getColumns();

        $columnPspReference = new \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn(
            'adyen_psp_reference'
        );
        $columnPspReference->setName(
            $this->trans($this->l('Adyen Psp Reference'))
        )
            ->setOptions(array(
                'actions' => (new \PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection()),
            ));

        $columnPaymentMethod = new \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn(
            'adyen_payment_method'
        );
        $columnPaymentMethod->setName(
            $this->trans($this->l('Adyen Payment Method'))
        )
            ->setOptions(array(
                'actions' => (new \PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection()),
            ));

        $columns->addAfter('payment', $columnPspReference);
        $columns->addAfter('adyen_psp_reference', $columnPaymentMethod);

        $definition->setColumns($columns);
    }

    /**
     * @param array $params
     *
     * @return void
     *
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookActionOrderGridPresenterModifier(array $params): void
    {
        \AdyenPayment\Classes\Bootstrap::init();

        $records = $params['presented_grid']['data']['records']->all();

        foreach ($records as &$record) {
            if ((new \Order((int)$record['id_order']))->module !== 'adyenofficial') {
                $record['pspReference'] = '--';
                $record['paymentMethod'] = '--';

                continue;
            }

            $transactionDetails = \AdyenPayment\Classes\Services\TransactionDetailsHandler::getTransactionDetails(
                new \Order((int)$record['id_order'])
            );
            if (empty($transactionDetails)) {
                $record['pspReference'] = '--';
                $record['paymentMethod'] = '--';

                continue;
            }

            $authorisationDetail = $transactionDetails[array_search(
                \Adyen\Webhook\EventCodes::AUTHORISATION,
                array_column($transactionDetails, 'eventCode'),
                true
            )];

            $record['pspReference'] = $authorisationDetail['pspReference'] ?? '';
            $record['paymentMethod'] = $authorisationDetail['paymentMethodType'] ?? '';
        }

        $params['presented_grid']['data']['records'] = new \PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection(
            $records
        );
    }

    /**
     * Hook for adding Adyen payment options if availability condition is satisfied.
     *
     * @param array $params Array containing cookie and cart objects
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookPaymentOptions(array $params): array
    {
        \AdyenPayment\Classes\Bootstrap::init();
        $storeService = \Adyen\Core\Infrastructure\ServiceRegister::getService(
            Adyen\Core\BusinessLogic\Domain\Integration\Store\StoreService::class
        );
        $precision = _PS_PRICE_COMPUTE_PRECISION_;
        if (version_compare(_PS_VERSION_, '1.7.7.0', 'ge')) {
            $precision = Context::getContext()->getComputingPrecision();
        }

        $cart = $params['cart'];
        $store = $cart->id_shop;
        $paymentOptions = [];

        if ($cart->id_address_invoice !== "0" && $storeService->checkStoreConnection($store)) {
            $config = \AdyenPayment\Classes\Services\CheckoutHandler::getPaymentCheckoutConfig($params['cart']);

            if (!$config->isSuccessful()) {
                return [];
            }

            $availablePaymentMethods = \AdyenPayment\Classes\Services\CheckoutHandler::getAvailablePaymentMethods(
                $config
            );
            $storedPaymentMethods = $config->getStoredPaymentMethodResponse();

            if ($this->creditCardEnabled($availablePaymentMethods)) {
                foreach ($storedPaymentMethods as $method) {
                    $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                    $logo = '';
                    $description = '';
                    $currency = new Currency($cart->id_currency);
                    foreach ($availablePaymentMethods as $availablePaymentMethod) {
                        if ($availablePaymentMethod->getCode() === $method->getType()) {
                            $logo = $availablePaymentMethod->getLogo();
                            $description = $availablePaymentMethod->getDescription();
                            $surchargeLimit = Tools::ps_round(
                                \AdyenPayment\Classes\SurchargeCalculator::calculateSurcharge(
                                    $availablePaymentMethod,
                                    $currency->conversion_rate,
                                    \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount::fromFloat(
                                        $cart->getOrderTotal(),
                                        \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                                            $currency->iso_code
                                        )
                                    )
                                ),
                                $precision
                            );

                            break;
                        }
                    }
                    $this->getContext()->smarty->assign([
                        'paymentMethodId' => $method->getMetadata()['id'],
                        'paymentMethodType' => $method->getType(),
                        'configURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                            'paymentconfig',
                            ['cartId' => \Context::getContext()->cart->id]
                        ),
                        'paymentActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl('payment'),
                        'paymentRedirectActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                            'paymentredirect',
                            ['adyenPaymentType' => $method->getType()]
                        ),
                        'stored' => true,
                        'description' => $description,
                        'prestaVersion' => _PS_VERSION_,
                        'checkoutUrl' => $this->context->link->getPageLink('order', true, null)
                    ]);

                    $paymentOption->setForm(
                        $this->getContext()->smarty->fetch($this->getTemplatePath('payment_method.tpl'))
                    );
                    $paymentOption->setLogo($logo);
                    $paymentOption->setModuleName($this->name);
                    $paymentOption->setCallToActionText(
                        (sprintf(
                                $this->l('Pay by saved %s ending: %s'),
                                $method->getName(),
                                $method->getMetadata()['lastFour']
                            ) . ($surchargeLimit ? " (+$surchargeLimit" . $currency->sign . ')' : ''))
                    );
                    $paymentOption->setForm(
                        $this->getContext()->smarty->fetch($this->getTemplatePath('payment_method.tpl'))
                    );
                    $paymentOption->setLogo($logo);
                    $paymentOption->setModuleName($this->name);

                    $paymentOptions[] = $paymentOption;
                }
            }

            foreach ($config->getRecurringPaymentMethodResponse() as $method) {
                $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $logo = '';
                $description = '';
                $name = '';
                $currency = new Currency($cart->id_currency);
                foreach ($availablePaymentMethods as $availablePaymentMethod) {
                    if ($availablePaymentMethod->getCode() === $method->getName()) {
                        $logo = $availablePaymentMethod->getLogo();
                        $description = $availablePaymentMethod->getDescription();
                        $name = $availablePaymentMethod->getName();
                        $surchargeLimit = Tools::ps_round(
                            \AdyenPayment\Classes\SurchargeCalculator::calculateSurcharge(
                                $availablePaymentMethod,
                                $currency->conversion_rate,
                                \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount::fromFloat(
                                    $cart->getOrderTotal(),
                                    \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                                        $currency->iso_code
                                    )
                                )
                            ),
                            $precision
                        );

                        break;
                    }
                }
                $this->getContext()->smarty->assign([
                    'paymentMethodId' => $method->getMetaData()['RecurringDetail']['recurringDetailReference'],
                    'paymentMethodType' => $method->getType(),
                    'configURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                        'paymentconfig',
                        ['cartId' => \Context::getContext()->cart->id]
                    ),
                    'paymentActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl('payment'),
                    'paymentRedirectActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenPaymentType' => $method->getType()]
                    ),
                    'stored' => true,
                    'description' => $description,
                    'prestaVersion' => _PS_VERSION_,
                    'checkoutUrl' => $this->context->link->getPageLink('order', true, null)
                ]);

                $paymentOption->setForm(
                    $this->getContext()->smarty->fetch($this->getTemplatePath('payment_method.tpl'))
                );
                $paymentOption->setLogo($logo);
                $paymentOption->setModuleName($this->name);
                $paymentOption->setCallToActionText(
                    (sprintf(
                            $this->l('Pay by saved %s created on: %s'),
                            $name,
                            (new \DateTime($method->getMetaData()['RecurringDetail']['creationDate']))->format('Y-m-d')
                        ) . ($surchargeLimit ? " (+$surchargeLimit" . $currency->sign . ')' : ''))
                );
                $paymentOption->setForm(
                    $this->getContext()->smarty->fetch($this->getTemplatePath('payment_method.tpl'))
                );
                $paymentOption->setLogo($logo);
                $paymentOption->setModuleName($this->name);

                $paymentOptions[] = $paymentOption;
            }

            foreach ($availablePaymentMethods as $method) {
                $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $this->getContext()->smarty->assign([
                    'paymentMethodId' => $method->getMethodId(),
                    'paymentMethodType' => $method->getCode(),
                    'configURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                        'paymentconfig',
                        ['cartId' => \Context::getContext()->cart->id]
                    ),
                    'paymentActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl('payment'),
                    'paymentRedirectActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenPaymentType' => $method->getCode()]
                    ),
                    'stored' => false,
                    'description' => $method->getDescription(),
                    'prestaVersion' => _PS_VERSION_,
                    'checkoutUrl' => $this->context->link->getPageLink('order', true, null)
                ]);

                $currency = new Currency($cart->id_currency);
                $surchargeLimit = Tools::ps_round(
                    \AdyenPayment\Classes\SurchargeCalculator::calculateSurcharge(
                        $method,
                        $currency->conversion_rate,
                        \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount::fromFloat(
                            $cart->getOrderTotal(),
                            \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                                $currency->iso_code
                            )
                        )
                    ),
                    $precision
                );

                $paymentOption->setCallToActionText(
                    (sprintf($this->l('Pay by %s'), $method->getName()))
                    . ($surchargeLimit ? " (+$surchargeLimit" . $currency->sign . ')' : '')
                );
                $paymentOption->setForm(
                    $this->getContext()->smarty->fetch($this->getTemplatePath('payment_method.tpl'))
                );
                $paymentOption->setLogo($method->getLogo());
                $paymentOption->setModuleName($this->name);

                $paymentOptions[] = $paymentOption;
            }
        }

        return $paymentOptions;
    }

    /**
     * @param array $params
     *
     * @return false|string|null
     */
    public function hookDisplayPaymentReturn(array $params)
    {
        if (!$this->active) {
            return null;
        }

        $cartId = \AdyenPayment\Classes\Utility\SessionService::get('cartId');

        $this->context->smarty->assign(
            [
                'adyenAction' => \AdyenPayment\Classes\Utility\SessionService::get('adyenAction'),
                'checkoutConfigUrl' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                    'paymentconfig',
                    ['cartId' => $cartId]
                ),
                'additionalDataUrl' => AdyenPayment\Classes\Utility\Url::getFrontUrl(
                    'paymentredirect',
                    [
                        'adyenMerchantReference' => $cartId,
                        'adyenPaymentType' => \AdyenPayment\Classes\Utility\SessionService::get('adyenPaymentMethodType'),
                        'adyenPage' => 'thankYou'
                    ]
                ),
            ]
        );

        return $this->display(__FILE__, '/views/templates/front/adyen-order-confirmation.tpl');
    }

    /**
     * @param array $params
     *
     * @return false|string
     *
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookDisplayCustomerAccount(array $params)
    {
        \AdyenPayment\Classes\Bootstrap::init();

        $this->context->smarty->assign(
            [
                'storedMethods' => \AdyenPayment\Classes\Utility\Url::getFrontUrl('storedmethods')
            ]
        );

        return $this->display(__FILE__, 'views/templates/front/customer-account.tpl');
    }

    /**
     * Loads CSS and JS files on checkout page.
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        if ($this->context->controller->page_name === 'module-adyenofficial-storedmethods') {
            $this->getContext()->controller->addCSS($this->getPathUri() . 'views/css/credit-cards.css');
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-delete-stored-method.js'
            );
        }

        if ($this->context->controller->php_self === 'order-confirmation') {
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-donations-controller.js'
            );
            $this->getContext()->controller->addJS($this->getPathUri() . 'views/js/front/adyen-donations-selection.js');
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-order-confirmation.js'
            );
        }

        if ($this->context->controller->php_self === 'order') {
            $this->getContext()->controller->addJS($this->getPathUri() . 'views/js/front/adyen-payment-selection.js');
            $this->getContext()->controller->addJS($this->getPathUri() . 'views/js/front/adyen-wallets.js');

            if ($message = $this->l(\AdyenPayment\Classes\Utility\SessionService::get('errorMessage'))) {
                $this->getContext()->controller->errors[] = $message;
            }
        }

        if ($this->context->controller->php_self === 'cart') {
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-cart-express-checkout.js'
            );
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-wallets-service.js'
            );

            if ($message = $this->l(\AdyenPayment\Classes\Utility\SessionService::get('errorMessage'))) {
                $this->getContext()->controller->errors[] = $message;
            }
        }

        if ($this->context->controller->php_self === 'product') {
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-product-express-checkout.js'
            );
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-wallets-service.js'
            );

            if ($message = $this->l(\AdyenPayment\Classes\Utility\SessionService::get('errorMessage'))) {
                $this->getContext()->controller->warning[] = $message;
            }
        }

        if ($this->context->controller->php_self === 'product' ||
            $this->context->controller->php_self === 'cart' ||
            $this->context->controller->php_self === 'order-confirmation' ||
            $this->context->controller->php_self === 'order' ||
            $this->context->controller->page_name === 'module-adyenofficial-payment') {
            $this->getContext()->controller->addCSS($this->getPathUri() . 'views/css/adyen-checkout.css');
            $this->getContext()->controller->addJS($this->getPathUri() . 'views/js/front/adyen-checkout-controller.js');
            $this->getContext()->controller->addJS(
                $this->getPathUri() . 'views/js/front/adyen-payment-additional-action.js'
            );
            $this->getContext()->controller->registerJavascript(
                'adyen-component-js',
                'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/5.31.1/adyen.js',
                [
                    'server' => 'remote',
                    'position' => 'head',
                    'attributes' => [
                        'integrity' => 'sha384-d6l5Qqod+Ks601U/jqsLz7QkW0LL6T5pfEsSHypuTSnDUYVGRLNV1ZdITbEwb1yL',
                        'crossorigin' => 'anonymous'
                    ]
                ]
            );
            $this->getContext()->controller->registerStylesheet(
                'adyen-component-css',
                'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/5.31.1/adyen.css',
                [
                    'server' => 'remote',
                    'position' => 'head',
                    'attributes' => [
                        'integrity' => 'sha384-d6l5Qqod+Ks601U/jqsLz7QkW0LL6T5pfEsSHypuTSnDUYVGRLNV1ZdITbEwb1yL',
                        'crossorigin' => 'anonymous'
                    ]
                ]
            );
        }
    }

    /**
     * Hook for adding Adyen express checkout buttons if availability condition is satisfied.
     *
     * @return string
     *
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayProductActions(): string
    {
        $configUrl = AdyenPayment\Classes\Utility\Url::getFrontUrl('paymentconfigexpresscheckout');
        $paymentUrl = AdyenPayment\Classes\Utility\Url::getFrontUrl('paymentproduct');

        return $this->displayExpress($configUrl, $paymentUrl);
    }

    /**
     * Hook for adding Adyen express checkout buttons if availability condition is satisfied.
     *
     * @return string
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookDisplayProductAdditionalInfo(): string
    {
        $configUrl = AdyenPayment\Classes\Utility\Url::getFrontUrl('paymentconfigexpresscheckout');
        $paymentUrl = AdyenPayment\Classes\Utility\Url::getFrontUrl('paymentproduct');

        return $this->displayExpress($configUrl, $paymentUrl);
    }

    /**
     * Hook for adding Adyen express checkout buttons if availability condition is satisfied.
     *
     * @return string
     *
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayExpressCheckout(): string
    {
        $configUrl = AdyenPayment\Classes\Utility\Url::getFrontUrl(
            'paymentconfigexpresscheckout',
            ['cartId' => \Context::getContext()->cart->id]
        );
        $paymentUrl = AdyenPayment\Classes\Utility\Url::getFrontUrl('payment');

        return $this->displayExpress($configUrl, $paymentUrl);
    }

    /**
     * Hook for displaying tab link on order page.
     *
     * @param array $params Hook parameters containing ID of the order.
     *
     * @return string Tab link HTML as string
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     *
     * @since 1.7.7
     */
    public function hookDisplayAdminOrderTabLink(array $params): string
    {
        return $this->displayTabLink($params['id_order']);
    }

    /**
     * Hook for displaying tab link on order page
     * Removed in 1.7.7 in favor of displayAdminOrderTabLink.
     *
     * @param array $params Hook parameters containing ID of the order
     *
     * @return string Tab link HTML as string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookDisplayAdminOrderTabShip(array $params): string
    {
        return $this->displayTabLink($params['order']->id);
    }

    /**
     * Hook for displaying tab content on order page.
     *
     * @param array $params Hook parameters
     *
     * @return string
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     *
     * @since 1.7.7
     */
    public function hookDisplayAdminOrderTabContent(array $params): string
    {
        return $this->displayTabContent($params['id_order']);
    }

    /**
     * Hook for displaying tab content on order page
     * Removed in 1.7.7 in favor of displayAdminOrderTabContent
     *
     * @param array $params Hook parameters
     *
     * @return string Tab content HTML as string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookDisplayAdminOrderContentShip(array $params): string
    {
        return $this->displayTabContent($params['order']->id);
    }

    /**
     * @param array $params
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode
     */
    public function hookActionOrderStatusUpdate(array $params): void
    {
        $order = new Order($params['id_order']);
        $newOrderStatus = $params['newOrderStatus'];

        if ($order->module !== $this->name || $newOrderStatus->id == $order->current_state || \Tools::getValue(
                'controller'
            ) === 'asyncprocess') {
            return;
        }

        \AdyenPayment\Classes\Services\OrderStatusHandler::handleOrderStatusChange($order, $newOrderStatus->id);
    }

    /**
     * Hook for adding JS && CSS to admin controllers.
     *
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookActionAdminControllerSetMedia(): void
    {
        $order = new \Order((int)Tools::getValue('id_order'));

        $currentController = Tools::getValue('controller');

        if ($currentController === 'AdminOrders') {
            $this->getContext()->controller->addJS(
                [
                    $this->getPathUri() . $this->getVersionHandler()->backofficeOrderJS()
                ]
            );
        }
        if ($order->module !== $this->name &&
            !\AdyenPayment\Classes\Services\OrderStatusHandler::shouldGeneratePaymentLinkForNonAdyenOrder($order)) {

            return;
        }

        if ($message = $this->l(\AdyenPayment\Classes\Utility\SessionService::get('errorMessage'))) {
            $this->getContext()->controller->errors[] = $message;
        }
        if ($message = $this->l(\AdyenPayment\Classes\Utility\SessionService::get('successMessage'))) {
            $this->getContext()->controller->informations[] = $message;
        }

        $this->getContext()->controller->addJS(
            [
                $this->getPathUri() . 'views/js/admin/adyen-order-tab-content.js',
                $this->getPathUri() . 'views/js/admin/adyen-ajax-service.js',
            ]
        );
    }

    /**
     * Hook for handling partial refund through Return products option.
     *
     * @param array $params Array containing order, cart and product list of partial refund
     *
     * @return void
     *
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode
     * @throws \Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookActionOrderSlipAdd(array $params): void
    {
        $order = $params['order'];

        if ($order->module !== $this->name) {
            return;
        }

        \AdyenPayment\Classes\Services\RefundHandler::handleRefund($order, $params['qtyList'] ?? []);
    }

    /**
     * @param array $params
     *
     * @return false|string|null
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
        if (!$this->active) {
            return null;
        }

        $storeId = $params['order']->id_shop;
        $adyenGivingInformation = \Adyen\Core\BusinessLogic\AdminAPI\AdminAPI::get()->adyenGivingSettings(
            $storeId
        )->getAdyenGivingSettings()->toArray();

        $cart = new \Cart($params['order']->id_cart);
        $this->context->smarty->assign(
            [
                'enabled' => $adyenGivingInformation['enableAdyenGiving'],
                'donationsConfigUrl' => \AdyenPayment\Classes\Utility\Url::getFrontUrl(
                    'adyendonationsconfig',
                    [
                        'merchantReference' => $cart->id,
                        'key' => $cart->secure_key,
                        'module' => $params['order']->module
                    ]
                ),
                'makeDonationsUrl' => \AdyenPayment\Classes\Utility\Url::getFrontUrl(
                    'adyenmakedonation',
                    [
                        'merchantReference' => $cart->id,
                        'key' => $cart->secure_key,
                        'module' => $params['order']->module
                    ]
                )
            ]
        );

        return $this->display(__FILE__, '/views/templates/front/adyen-donations.tpl');
    }

    /**
     * @return array[]
     *
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    public function hookModuleRoutes(): array
    {
        \AdyenPayment\Classes\Bootstrap::init();

        return [
            'module-adyenofficial-applepay' => [
                'controller' => 'applepay',
                'rule' => '.well-known/apple-developer-merchantid-domain-association',
                'keywords' => [
                    'link_rewrite' => [
                        'regexp' => '[_a-zA-Z0-9-\pL]*',
                        'param' => 'link_rewrite'
                    ],
                ],
                'params' => [
                    'fc' => 'module',
                    'module' => 'adyenofficial',
                ]
            ]
        ];
    }

    /**
     * Hook for displaying header data used in BO.
     *
     * @return false|string Header HTML data as string
     * @throws \PrestaShopException
     * @throws Exception
     */
    public function hookDisplayBackOfficeHeader(): string
    {
        if (!$this->isEnabled($this->name) || Tools::getValue('controller') !== 'AdminOrders') {
            return '';
        }
        $generalSettings = \Adyen\Core\BusinessLogic\AdminAPI\AdminAPI::get()->generalSettings((string)\Context::getContext()->shop->id)->getGeneralSettings();

        if (!$generalSettings->isSuccessful() || !$generalSettings->toArray()['enablePayByLink']) {
            return '';
        }
        $expirationTime = $generalSettings->toArray()['defaultLinkExpirationTime'];
        $date = Adyen\Core\Infrastructure\Utility\TimeProvider::getInstance()->getCurrentLocalTime()->add(
            new DateInterval('P' . $expirationTime . 'D')
        )->format("Y-m-d");
        $this->getContext()->smarty->assign(['payByLinkTitle' => $generalSettings->toArray()['payByLinkTitle'], 'expirationDate' => $date]);

        return $this->display($this->getPathUri(), $this->getVersionHandler()->backofficeOrderTemplate());
    }


    /**
     * @param array $params
     *
     * @return void
     *
     * @throws \Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode
     */
    public function hookActionValidateOrder(array $params): void
    {
        if (!isset($this->getContext()->controller) ||
            'admin' !== $this->getContext()->controller->controller_type ||
            $params['order']->module !== $this->name) {

            return;
        }

        $expiresAt = \Tools::getValue('adyen-expires-at-date');
        /** @var \Order $order */
        $order = $params['order'];
        $currency = new  \Currency($order->id_currency);
        $paymentLink = \Adyen\Core\BusinessLogic\AdminAPI\AdminAPI::get()->paymentLink((string)$order->id_shop)
            ->createPaymentLink(
                new \Adyen\Core\BusinessLogic\AdminAPI\PaymentLink\Request\CreatePaymentLinkRequest
                ($order->getOrdersTotalPaid(), $currency->iso_code, $order->id_cart, new \DateTime($expiresAt)));

        if ($paymentLink->isSuccessful()) {
            \AdyenPayment\Classes\Utility\SessionService::set(
                'successMessage',
                $this->l('Payment link successfully generated.')
            );

            return;
        }

        \AdyenPayment\Classes\Utility\SessionService::set(
            'errorMessage',
            $this->l('Payment link generation failed. Reason: ') . $paymentLink->toArray()['errorMessage'] ?? ''
        );
    }

    /**
     * Hook for altering email template variables before sending.
     *
     * @param array $params Array of parameters including template_vars array and cart
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     *
     * @throws \Exception
     */
    public function hookSendMailAlterTemplateVars(array &$params): void
    {
        if (isset($params['template_vars']['{adyen_payment_link}']) || !isset($params['template_vars']['{id_order}'])) {

            return;
        }

        $order = new \Order($params['template_vars']['{id_order}']);

        \AdyenPayment\Classes\Bootstrap::init();
        $transactionDetails = \AdyenPayment\Classes\Services\TransactionDetailsHandler::getTransactionDetails($order);

        if (empty($transactionDetails)) {
            $params['template_vars']['{adyen_payment_link}'] = '';

            return;
        }

        $reversedDetails = array_reverse($transactionDetails);
        $authorisationDetail = $reversedDetails[array_search(
            \Adyen\Webhook\EventCodes::AUTHORISATION,
            array_column($reversedDetails, 'eventCode'),
            true
        )];

        $params['template_vars']['{adyen_payment_link}'] = $authorisationDetail['paymentLink'];
    }

    /**
     * Creates Adyen Installer.
     *
     * @return AdyenPayment\Classes\Utility\Installer
     */
    private function getInstaller(): AdyenPayment\Classes\Utility\Installer
    {
        return new AdyenPayment\Classes\Utility\Installer($this);
    }

    /**
     * Loads Adyen stylesheets.
     *
     * @return void
     */
    private function loadStyles(): void
    {
        $this->context->controller->addCSS(
            [
                $this->getPathUri() . 'views/css/adyen-core.css',
                $this->getPathUri() . 'views/css/adyen-presta.css'
            ],
            'all',
            null,
            false
        );
    }

    /**
     * Loads Adyen scripts.
     *
     * @return void
     */
    private function loadScripts(): void
    {
        $this->context->controller->addJS(
            [
                $this->getPathUri() . 'views/js/AjaxService.js',
                $this->getPathUri() . 'views/js/TranslationService.js',
                $this->getPathUri() . 'views/js/ElementGenerator.js',
                $this->getPathUri() . 'views/js/StateController.js',
                $this->getPathUri() . 'views/js/ConnectionController.js',
                $this->getPathUri() . 'views/js/DataTableComponent.js',
                $this->getPathUri() . 'views/js/DropdownComponent.js',
                $this->getPathUri() . 'views/js/ModalComponent.js',
                $this->getPathUri() . 'views/js/MultiselectDropdownComponent.js',
                $this->getPathUri() . 'views/js/NotificationsController.js',
                $this->getPathUri() . 'views/js/PageControllerFactory.js',
                $this->getPathUri() . 'views/js/PaymentsController.js',
                $this->getPathUri() . 'views/js/ResponseService.js',
                $this->getPathUri() . 'views/js/SettingsController.js',
                $this->getPathUri() . 'views/js/StateUUIDService.js',
                $this->getPathUri() . 'views/js/TableFilterComponent.js',
                $this->getPathUri() . 'views/js/TemplateService.js',
                $this->getPathUri() . 'views/js/UtilityService.js',
                $this->getPathUri() . 'views/js/ValidationService.js'
            ],
            false
        );
    }

    /**
     * Returns Adyen module controller URLs.
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    private function getUrls(): array
    {
        return [
            'connection' => [
                'getSettingsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenAuthorization',
                    'getConnectionSettings',
                    '{storeId}'
                ),
                'submitUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenAuthorization',
                    'connect',
                    '{storeId}'
                ),
                'disconnectUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenDisconnect',
                    'disconnect',
                    '{storeId}'
                ),
                'getMerchantsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenMerchant',
                    'index',
                    '{storeId}'
                ),
                'validateConnectionUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenValidateConnection',
                    'validate',
                    '{storeId}'
                ),
                'validateWebhookUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenWebhookValidation',
                    'validate',
                    '{storeId}'
                )
            ],
            'payments' => [
                'getAvailablePaymentsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenPayment',
                    'getAvailableMethods',
                    '{storeId}'
                ),
                'getConfiguredPaymentsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenPayment',
                    'getConfiguredMethods',
                    '{storeId}'
                ),
                'getMethodConfigurationUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenPayment',
                    'getMethodById',
                    '{storeId}',
                    '{methodId}'
                ),
                'addMethodConfigurationUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenPayment',
                    'saveMethod',
                    '{storeId}'
                ),
                'saveMethodConfigurationUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenPayment',
                    'updateMethod',
                    '{storeId}'
                ),
                'deleteMethodConfigurationUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenPayment',
                    'deleteMethod',
                    '{storeId}',
                    '{methodId}'
                )
            ],
            'stores' => [
                'storesUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenShopInformation',
                    'getStores'
                ),
                'currentStoreUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenShopInformation',
                    'getCurrentStore'
                ),
                'switchContextUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenShopInformation',
                    'switchContext',
                    '{storeId}'
                )
            ],
            'integration' => [
                'stateUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenState',
                    'index',
                    '{storeId}'
                )
            ],
            'version' => [
                'versionUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenVersion',
                    'getVersion'
                )
            ],
            'settings' => [
                'getShippingStatusesUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenOrderStatuses',
                    'getOrderStatuses',
                    '{storeId}'
                ),
                'getSettingsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenGeneralSettings',
                    'getGeneralSettings',
                    '{storeId}'
                ),
                'saveSettingsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenGeneralSettings',
                    'putGeneralSettings',
                    '{storeId}'
                ),
                'getOrderMappingsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenOrderStatusMap',
                    'getOrderStatusMap',
                    '{storeId}'
                ),
                'saveOrderMappingsUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenOrderStatusMap',
                    'putOrderStatusMap',
                    '{storeId}'
                ),
                'getGivingUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenGivingSettings',
                    'getAdyenGivingSettings',
                    '{storeId}'
                ),
                'saveGivingUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenGivingSettings',
                    'putAdyenGivingSettings',
                    '{storeId}'
                ),
                'webhookValidationUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenWebhookValidation',
                    'validate',
                    '{storeId}'
                ),
                'downloadWebhookReportUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenWebhookValidation',
                    'validateReport',
                    '{storeId}'
                ),
                'integrationValidationUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenAutoTest',
                    'startAutoTest'
                ),
                'integrationValidationTaskCheckUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenAutoTest',
                    'autoTestStatus',
                    null,
                    null,
                    '{queueItemId}'
                ),
                'downloadIntegrationReportUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenAutoTest',
                    'getReport'
                ),
                'downloadSystemInfoFileUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenSystemInfo',
                    'systemInfo'
                ),
                'getSystemInfoUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenDebug',
                    'getDebugMode'
                ),
                'saveSystemInfoUrl' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenDebug',
                    'setDebugMode'
                )
            ],
            'notifications' => [
                'getShopEventsNotifications' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenNotifications',
                    'getNotifications',
                    '{storeId}'
                ),
                'getWebhookEventsNotifications' => AdyenPayment\Classes\Utility\Url::getAdminUrl(
                    'AdyenWebhookNotifications',
                    'getWebhookNotifications',
                    '{storeId}'
                )
            ]
        ];
    }

    /**
     * Returns Adyen module sidebar.
     *
     * @return false|string
     */
    private function getSidebarContent()
    {
        return file_get_contents(__DIR__ . '/views/templates/sidebar.html');
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getTranslations(): array
    {
        return [
            'default' => $this->getDefaultTranslations(),
            'current' => $this->getCurrentTranslations()
        ];
    }

    /**
     * @return mixed
     */
    private function getDefaultTranslations()
    {
        $baseDir = __DIR__ . '/views/lang/';

        return json_decode(file_get_contents($baseDir . 'en.json'), true);
    }

    /**
     * @return mixed
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCurrentTranslations()
    {
        $baseDir = __DIR__ . '/views/lang/';
        $locale = $this->getLocale();
        $file = file_exists($baseDir . $locale . '.json') ? $baseDir . $locale . '.json' : $baseDir . 'en.json';

        return json_decode(file_get_contents($file), true);
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getLocale(): string
    {
        $locale = new Language(Context::getContext()->employee->id_lang);

        return in_array($locale->iso_code, ['en', 'de', 'nl']) ? $locale->iso_code : 'en';
    }

    /**
     * @param array $availablePaymentMethods
     *
     * @return bool
     */
    private function creditCardEnabled(array $availablePaymentMethods): bool
    {
        foreach ($availablePaymentMethods as $method) {
            if ($method->getCode() === "scheme") {
                return true;
            }
        }
        return false;
    }

    /**
     * Display tab link on order page.
     *
     * @param int $orderId
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    private function displayTabLink(int $orderId): string
    {
        $order = new Order($orderId);

        if ($order->module !== $this->name &&
            !\AdyenPayment\Classes\Services\OrderStatusHandler::shouldGeneratePaymentLinkForNonAdyenOrder($order)) {
            return '';
        }

        \AdyenPayment\Classes\Bootstrap::init();

        $this->getContext()->smarty->assign(
            ['adyen_name' => $this->l('Adyen Payment')]
        );

        return $this->display(__FILE__, $this->getVersionHandler()->tabLink());
    }

    /**
     * Displays tab content on order page.
     *
     * @param int $orderId
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws Exception
     */
    private function displayTabContent(int $orderId): string
    {
        $order = new \Order($orderId);

        if ($order->module !== $this->name &&
            !\AdyenPayment\Classes\Services\OrderStatusHandler::shouldGeneratePaymentLinkForNonAdyenOrder($order)) {
            return $this->display(__FILE__, 'adyen-empty-tab.tpl');
        }

        $currency = new \Currency($order->id_currency);
        $transactionDetails = \AdyenPayment\Classes\Services\TransactionDetailsHandler::getTransactionDetails($order);

        $reversedDetails = array_reverse($transactionDetails);
        $authorisationDetail = !empty($reversedDetails) ? $reversedDetails[array_search(
            \Adyen\Webhook\EventCodes::AUTHORISATION,
            array_column($reversedDetails, 'eventCode'),
            true
        )] : [];

        $lastDetail = end($transactionDetails);
        $generalSettings = \Adyen\Core\BusinessLogic\AdminAPI\AdminAPI::get()->generalSettings((string)\Context::getContext()->shop->id)->getGeneralSettings();
        $paymentLinkEnabled = $generalSettings->isSuccessful() && $generalSettings->toArray()['enablePayByLink'];

        \AdyenPayment\Classes\Bootstrap::init();
        $this->getContext()->smarty->assign([
            'transactionDate' => $authorisationDetail['date'] ?? '',
            'originalReference' => $authorisationDetail['pspReference'] ?? '',
            'paymentMethod' => $authorisationDetail['paymentMethodType'] ?? '',
            'methodLogo' => $authorisationDetail['paymentMethod'] ?? '',
            'orderAmount' => $authorisationDetail['paidAmount'] ?? '',
            'refundedAmount' => $authorisationDetail['refundAmount'] ?? '',
            'status' => ucfirst(strtolower($lastDetail['paymentState'] ?? '')),
            'statusDate' => $lastDetail['date'] ?? '',
            'merchantID' => $authorisationDetail['merchantAccountCode'] ?? '',
            'riskScore' => $authorisationDetail['riskScore'] ?? '',
            'captureAvailable' => $authorisationDetail['captureSupported'] ?? '',
            'capturableAmount' => $authorisationDetail['capturableAmount'] ?? '',
            'currency' => $currency->symbol ?? ($currency->sign ?? ''),
            'currencyISO' => $currency->iso_code,
            'transactionHistory' => $transactionDetails,
            'captureURL' => $this->getAction('AdyenCapture', 'captureOrder', ['ajax' => true]),
            'orderId' => $orderId,
            'adyenLink' => $authorisationDetail['viewOnAdyenUrl'] ?? '',
            'refundSupported' => $authorisationDetail['refund'] ?? false,
            'adyenPaymentLink' => $authorisationDetail['paymentLink'] ?? '',
            'adyenGeneratePaymentLink' => $this->getAction('AdyenPaymentLink', 'generatePaymentLink', ['ajax' => true]),
            'shouldDisplayPaymentLink' => $authorisationDetail['displayPaymentLink'] ?? false,
            'isAdyenOrder' => $order->module === $this->name,
            'shouldDisplayPaymentLinkForNonAdyenOrder' => $authorisationDetail['displayPaymentLink'] ?? $paymentLinkEnabled
        ]);

        return $this->display(__FILE__, $this->getVersionHandler()->tabContent());
    }

    /**
     * Returns Version175 instance if PrestaShop version is < than 1.7.7.
     * Otherwise instance of Version177 is returned.
     *
     * @return \AdyenPayment\Classes\Version\Contract\VersionHandler
     */
    private function getVersionHandler(): \AdyenPayment\Classes\Version\Contract\VersionHandler
    {
        return \Adyen\Core\Infrastructure\ServiceRegister::getService(
            \AdyenPayment\Classes\Version\Contract\VersionHandler::class
        );
    }

    /**
     * Retrieves link to controller and it's appropriate method.
     *
     * @param string $controller Controller name
     * @param string $action Method name
     * @param array $params URL parameters
     *
     * @return string Action link
     *
     * @throws \PrestaShopException
     */
    private function getAction(string $controller, string $action, array $params): string
    {
        $query = array_merge(array('action' => $action), $params);

        return $this->getContext()->link->getAdminLink($controller) .
            '&' . http_build_query($query);
    }

    /**
     * @param $configUrl
     * @param $paymentUrl
     * @return string
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws PrestaShopDatabaseException
     */
    private function displayExpress($configUrl, $paymentUrl): string
    {
        $this->getContext()->smarty->assign([
            'adyenShowExpressCheckout' => $this->verifyIfExpressCheckoutShouldBeDisplayed(),
            'configURL' => $configUrl,
            'paymentActionURL' => $paymentUrl,
            'paymentRedirectActionURL' => AdyenPayment\Classes\Utility\Url::getFrontUrl('paymentredirect'),
            'version' => _PS_VERSION_
        ]);

        return $this->display(__FILE__, 'views/templates/front/express_checkout.tpl');
    }

    /**
     * @return bool
     * @throws \Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws PrestaShopDatabaseException
     */
    private function verifyIfExpressCheckoutShouldBeDisplayed(): bool
    {
        \AdyenPayment\Classes\Bootstrap::init();
        $storeService = \Adyen\Core\Infrastructure\ServiceRegister::getService(
            Adyen\Core\BusinessLogic\Domain\Integration\Store\StoreService::class
        );

        if (!$storeService->checkStoreConnection($this->context->shop->id)) {
            return false;
        }

        $langId = (int)$this->context->language->id;
        $customerId = (int)$this->context->customer->id;
        $customer = new Customer($customerId);

        if ($customerId === 0 || $customer->isGuest()) {
            return false;
        }

        $addresses = $customer->getAddresses($langId);

        if (count($addresses) === 0) {
            return false;
        }

        if (!$this->verifyIfCarrierNotRestricted() ||
            !$this->verifyIfCurrencyNotRestricted() ||
            !$this->verifyIfCountryNotRestricted()) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function verifyIfCurrencyNotRestricted(): bool
    {
        $activeCurrencies = $this->getCurrency();
        $currencyCodes = array_column($activeCurrencies, 'iso_code');
        $currentCurrency = $this->context->currency->iso_code;

        return in_array($currentCurrency, $currencyCodes, true);
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    private function verifyIfCountryNotRestricted(): bool
    {
        $activeCountries = $this->getModuleCountries((int)$this->id, (int)$this->context->shop->id);
        $countryCodes = array_column($activeCountries, 'iso_code');
        $currentCountry = $this->context->country->iso_code;

        return in_array($currentCountry, $countryCodes, true);
    }

    /**
     * Returns module not restricted countries
     *
     * @param int $moduleId
     * @param int $shopId
     * @return array|bool|mysqli_result|PDOStatement|resource|null
     * @throws PrestaShopDatabaseException
     */
    private function getModuleCountries(int $moduleId, int $shopId)
    {
        $sql = 'SELECT c.*
				FROM `' . _DB_PREFIX_ . 'module_country` mc
				LEFT JOIN `' . _DB_PREFIX_ . 'country` c ON c.`id_country` = mc.`id_country`
				WHERE mc.`id_module` = ' . (int)$moduleId . '
					AND c.`active` = 1
					AND mc.id_shop = ' . (int)$shopId . '
				ORDER BY c.`iso_code` ASC';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    private function verifyIfCarrierNotRestricted(): bool
    {
        $activeCarriers = $this->getModuleCarriers((int)$this->id, (int)$this->context->shop->id);

        if (count($activeCarriers) > 0) {
            return true;
        }

        $productId = Tools::getValue('id_product');
        if ($this->context->controller->php_self === 'product' && $productId) {
            return (new Product($productId))->is_virtual;
        }

        return $this->context->controller->php_self === 'cart' && $this->context->cart->isVirtualCart();
    }

    /**
     * Returns module not restricted carriers
     *
     * @param int $moduleId
     * @param int $shopId
     * @return array|bool|mysqli_result|PDOStatement|resource|null
     * @throws PrestaShopDatabaseException
     */
    private function getModuleCarriers(int $moduleId, int $shopId)
    {
        $sql = 'SELECT c.*
				FROM `' . _DB_PREFIX_ . 'module_carrier` mc
				LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c ON c.`id_reference` = mc.`id_reference`
				WHERE mc.`id_module` = ' . (int)$moduleId . '
					AND c.`active` = 1
					AND mc.id_shop = ' . (int)$shopId . '
				ORDER BY c.`name` ASC';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Used to define snippets for translating messages.
     *
     * @return void
     */
    private function initTranslations(): void
    {
        $this->l('Capture request successfully sent to Adyen.');
        $this->l('Capture request failed. Please check Adyen configuration. Reason: ');
        $this->l('Capture is not supported on Adyen.');
        $this->l('Capture request failed. Please check Adyen configuration. Reason: ');
        $this->l('Cancel is not supported on Adyen.');
        $this->l('Cancel request failed. Please check Adyen configuration. Reason: ');
        $this->l('Cancellation request successfully sent to Adyen.');
        $this->l('Refund is not supported on Adyen.');
        $this->l('Refund request failed. Please check Adyen configuration. Reason: ');
        $this->l('Refund request successfully sent to Adyen.');
        $this->l('Chargeback');
        $this->l('Pending');
        $this->l('Partially refunded');
        $this->l('Surcharge');
    }
}
