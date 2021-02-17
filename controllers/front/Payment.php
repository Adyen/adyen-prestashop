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

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\AdyenException;
use Adyen\PrestaShop\controllers\FrontController;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\Checkout;
use PrestaShop\PrestaShop\Adapter\CoreException;

class AdyenOfficialPaymentModuleFrontController extends FrontController
{
    const IS_AJAX = 'isAjax';
    const DATE_OF_BIRTH = 'dateOfBirth';
    const GENDER = 'gender';
    const TELEPHONE_NUMBER = 'telephoneNumber';
    const PAYMENT_METHOD = 'paymentMethod';
    const TYPE = 'type';
    const PERSONAL_DETAILS = 'personalDetails';

    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * @var Adyen\PrestaShop\service\Payment
     */
    private $paymentService;

    /**
     * @var Adyen\PrestaShop\service\builder\Customer
     */
    private $customerBuilder;

    /**
     * @var Adyen\PrestaShop\service\builder\OpenInvoice
     */
    private $openInvoiceBuilder;

    /**
     * @var Adyen\PrestaShop\service\builder\Address
     */
    private $addressBuilder;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Language
     */
    private $languageAdapter;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\State
     */
    private $stateAdapter;

    /**
     * @var Adyen\Util\Currency
     */
    private $utilCurrency;

    /**
     * @var Adyen\PrestaShop\service\builder\Browser
     */
    private $browserBuilder;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Country
     */
    private $countryAdapter;

    /**
     * @var Adyen\PrestaShop\service\builder\Payment
     */
    private $paymentBuilder;

    /**
     * @var Adyen\PrestaShop\service\Gender
     */
    private $genderService;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\Configuration
     */
    private $configuration;

    /**
     * @var Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter
     */
    private $orderAdapter;

    /**
     * AdyenPaymentModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->paymentService = ServiceLocator::get('Adyen\PrestaShop\service\Payment');
        $this->customerBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Customer');
        $this->openInvoiceBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\OpenInvoice');
        $this->addressBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Address');
        $this->languageAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Language');
        $this->stateAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\State');
        $this->utilCurrency = ServiceLocator::get('Adyen\Util\Currency');
        $this->browserBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Browser');
        $this->countryAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Country');
        $this->paymentBuilder = ServiceLocator::get('Adyen\PrestaShop\service\builder\Payment');
        $this->genderService = ServiceLocator::get('Adyen\PrestaShop\service\Gender');
        $this->configuration = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Configuration');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        $this->orderAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter');
    }

    /**
     * @return mixed
     * @throws CoreException
     * @throws AdyenException
     */
    public function postProcess()
    {
        $cart = $this->getCurrentCart();
        $isAjax = \Tools::getValue(self::IS_AJAX);
        $request = array();

        try {
            $request = $this->getValidatedAdditionalData(\Tools::getAllValues());
            $request = $this->buildBrowserData($request);
            $request = $this->buildAddresses($request);
            $request = $this->buildPaymentData($request);
            $request = $this->buildCustomerData($request);
            $request = $this->buildOpenInvoiceLines($request);
        } catch (MissingDataException $exception) {
            $this->logger->error(
                sprintf(
                    "There was an error with the payment method. id:  %s Missing data: %s",
                    $cart->id,
                    $exception->getMessage()
                )
            );

            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => "There was an error with the payment method, please choose another one."
                    )
                )
            );
        }

        // call adyen library
        /** @var Checkout $service */
        $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

        try {
            $response = $service->payments($request);
        } catch (AdyenException $e) {
            $this->logger->error(
                "There was an error with the payment method. id:  " . $cart->id .
                " Response: " . $e->getMessage()
            );

            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => "There was an error with the payment method, please choose another one."
                    )
                )
            );
        }

        $customer = new \Customer($cart->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            $this->redirectUserToPageLink(
                $this->context->link->getPageLink('order', $this->ssl, null, 'step=1'),
                $isAjax
            );
        }

        $this->handleAdyenApiResponse($response, $cart, $customer, $isAjax, $request);
    }

    /**
     * @param array $request
     * @return array|mixed
     * @throws MissingDataException
     */
    public function buildPaymentData($request = array())
    {
        $cart = $this->getCurrentCart();
        $origin = $this->configuration->httpHost;

        $formattedValue = $this->utilCurrency->sanitize(
            $cart->getOrderTotal(true, \Cart::BOTH),
            $this->context->currency->iso_code
        );

        // Retrieve merchant account
        $merchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');

        $returnUrl = $this->context->link->getModuleLink(
            $this->module->name,
            'Result',
            array(
                self::ADYEN_MERCHANT_REFERENCE => $cart->id
            ),
            $this->ssl
        );

        $request = $this->paymentBuilder->buildPaymentData(
            $this->context->currency->iso_code,
            $formattedValue,
            $cart->id,
            $merchantAccount,
            $returnUrl,
            $origin,
            $request
        );

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    private function buildBrowserData($request = array())
    {
        // Default values for acceptHeader and userAgent
        $acceptHeader = $_SERVER['HTTP_ACCEPT'];
        $userAgent = null;

        // If userAgent is empty in the request, retrieve it from the $_SERVER
        if (empty($request[self::BROWSER_INFO][self::USER_AGENT])) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
        }

        return $this->browserBuilder->buildBrowserData(
            $userAgent,
            $acceptHeader,
            $request
        );
    }

    /**
     * @param array $request
     * @return array
     */
    private function buildAddresses($request = array())
    {
        $cart = $this->getCurrentCart();

        $invoicingAddress = new \Address($cart->id_address_invoice);
        $deliveryAddress = new \Address($cart->id_address_delivery);

        // Invoicing address
        $invoicingAddressCountryCode = $this->countryAdapter->getIsoById($invoicingAddress->id_country);
        $invoicingAddressStateIsoCode = $this->stateAdapter->getIsoById($invoicingAddress->id_state);

        // If iso does not exists for id_state assign default empty string
        if (!$invoicingAddressStateIsoCode) {
            $invoicingAddressStateIsoCode = '';
        }

        $request = $this->addressBuilder->buildBillingAddress(
            $invoicingAddress->address1,
            $invoicingAddress->address2,
            $invoicingAddress->postcode,
            $invoicingAddress->city,
            $invoicingAddressStateIsoCode,
            $invoicingAddressCountryCode,
            $request
        );

        // Delivery address
        $deliveryAddressCountryCode = $this->countryAdapter->getIsoById($deliveryAddress->id_country);
        $deliveryAddressStateIsoCode = $this->stateAdapter->getIsoById($deliveryAddress->id_state);

        // If iso does not exists for id_state assign default empty string
        if (!$deliveryAddressStateIsoCode) {
            $deliveryAddressStateIsoCode = '';
        }

        $request = $this->addressBuilder->buildDeliveryAddress(
            $deliveryAddress->address1,
            $deliveryAddress->address2,
            $deliveryAddress->postcode,
            $deliveryAddress->city,
            $deliveryAddressStateIsoCode,
            $deliveryAddressCountryCode,
            $request
        );

        return $request;
    }

    /**
     * @param array $request
     * @return array
     * @throws MissingDataException
     */
    private function buildCustomerData($request = array())
    {
        $cart = $this->getCurrentCart();
        $customer = new \CustomerCore($cart->id_customer);
        $language = new \LanguageCore($cart->id_lang);
        $invoicingAddress = new \AddressCore($cart->id_address_invoice);

        $paymentMethod = \Tools::getValue(self::PAYMENT_METHOD);
        if (empty($paymentMethod)) {
            throw new MissingDataException('payment method is not sent in the request!');
        }

        if (empty($paymentMethod[self::TYPE])) {
            throw new MissingDataException('payment method type is not sent in the request!');
        }

        $paymentMethodType = $paymentMethod[self::TYPE];
        $isOpenInvoice = $this->paymentService->isOpenInvoicePaymentMethod($paymentMethodType);
        $localeCode = $this->languageAdapter->getLocaleCode($language);
        $invoicingAddressCountryCode = $this->countryAdapter->getIsoById($invoicingAddress->id_country);
        $shopperIp = \Tools::getRemoteAddr();

        $gender = '';
        $telephoneNumber = '';
        $dateOfBirth = '';

        if (empty($request[self::PAYMENT_METHOD][self::PERSONAL_DETAILS][self::GENDER])) {
            $gender = $this->genderService->getAdyenGenderValueById($customer->id_gender);
        }

        if (empty($request[self::PAYMENT_METHOD][self::PERSONAL_DETAILS][self::DATE_OF_BIRTH])) {
            if ('0000-00-00' !== $customer->birthday) {
                $dateOfBirth = $customer->birthday;
            }
        }

        if (empty($request[self::PAYMENT_METHOD][self::PERSONAL_DETAILS][self::TELEPHONE_NUMBER])) {
            // Retrieve the phone number from the invoicing address, if phone is null, try phone_mobile
            $telephoneNumber = $invoicingAddress->phone ? $invoicingAddress->phone:$invoicingAddress->phone_mobile;
        }

        return $this->customerBuilder->buildCustomerData(
            $isOpenInvoice,
            $customer->email,
            $telephoneNumber,
            $gender,
            $dateOfBirth,
            $invoicingAddress->firstname,
            $invoicingAddress->lastname,
            $invoicingAddressCountryCode,
            $localeCode,
            $shopperIp,
            $customer->id,
            $request
        );
    }

    /**
     * @param array $request
     * @return array|mixed
     */
    private function buildOpenInvoiceLines($request = array())
    {
        $cart = $this->getCurrentCart();
        $paymentMethod = \Tools::getValue(self::PAYMENT_METHOD);
        if (empty($paymentMethod)) {
            throw new MissingDataException('payment method is not sent in the request!');
        }

        $products = $cart->getProducts(true);
        $lineItems = array();

        // Build open invoice lines for products in the cart
        foreach ($products as $product) {
            $productPrice = $this->utilCurrency->sanitize($product['price'], $this->context->currency->iso_code);
            $productPriceWithTax = $this->utilCurrency->sanitize(
                $product['price_wt'],
                $this->context->currency->iso_code
            );
            $tax = $productPriceWithTax - $productPrice;

            $productDescription = trim(strip_tags($product['name']));

            $lineItems[] = $this->openInvoiceBuilder->buildOpenInvoiceLineItem(
                $productDescription,
                $productPrice,
                $tax,
                $product['rate'] * 100,
                $product['quantity'],
                $this->openInvoiceBuilder->getVatCategory($paymentMethod),
                $product['id_product']
            );
        }

        // Array of the discount items with the applied value in the cart
        $discounts = $cart->getCartRules();

        // TODO handle multiple discount lines applied value calculation

        // Build open invoice lines for discounts
        foreach ($discounts as $discount) {
            $discountValue = -$this->utilCurrency->sanitize(
                $discount['value_real'],
                $this->context->currency->iso_code
            );
            $lineItems[] = $this->openInvoiceBuilder->buildOpenInvoiceLineItem(
                $discount['name'],
                $discountValue,
                0,
                0,
                1,
                'None',
                $discount['id_discount']
            );
        }

        // Build open invoice lines for shipping
        $deliveryCost = $cart->getPackageShippingCost();
        $cartSummary = $cart->getSummaryDetails();
        $carrier = $cartSummary['carrier'];
        $totalShipping = $this->utilCurrency->sanitize(
            $cartSummary['total_shipping'],
            $this->context->currency->iso_code
        );
        $shippingTax = ($cartSummary['total_shipping'] - $cartSummary['total_shipping_tax_exc']) * 100;

        if ($cartSummary['total_shipping']) {
            $shippingTaxRate = $shippingTax * 100 / $cartSummary['total_shipping'];
        } else {
            $shippingTaxRate = 0;
        }

        if ($deliveryCost) {
            $lineItems[] = $this->openInvoiceBuilder->buildOpenInvoiceLineItem(
                $carrier->name,
                $totalShipping,
                $shippingTax,
                $shippingTaxRate,
                1,
                'None',
                $carrier->id_reference
            );
        }

        if (!empty($lineItems)) {
            $request['lineItems'] = $lineItems;
        }

        return $request;
    }
}
