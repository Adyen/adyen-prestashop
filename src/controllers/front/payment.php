<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PaymentRequest\Request\StartTransactionRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\Integration\CustomerService;
use AdyenPayment\Classes\Services\PayPalGuestExpressCheckoutService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\Url;
use AdyenPayment\Controllers\PaymentController;
use Adyen\Core\Infrastructure\Logger\Logger;
use Currency as PrestaCurrency;

/**
 * Class AdyenOfficialPaymentModuleFrontController
 */
class AdyenOfficialPaymentModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPaymentModuleFrontController';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:adyenofficial/views/templates/front/adyen-additional-details.tpl');
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function postProcess()
    {
        $data = Tools::getAllValues();
        $additionalData = !empty($data['adyen-additional-data']) ? json_decode(
            $data['adyen-additional-data'],
            true
        ) : [];
        $type = array_key_exists('adyen-type', $data) ? $data['adyen-type'] : '';
        if ($this->isAjaxRequest()) {
            $type = $additionalData['paymentMethod']['type'];
        }

        $cart = $this->context->cart;
        $customer = $this->context->customer;

        $customerEmail = array_key_exists('adyenEmail', $data) ?
            str_replace(['"', "'"], '', $data['adyenEmail']) : '';
        /** @var CustomerService $customerService */
        $customerService = ServiceRegister::getService(CustomerService::class);
        if ($customerEmail !== '') {
            $customer = $customerService->createAndLoginCustomer($customerEmail, $data);
        } elseif (PaymentMethodCode::payPal()->equals($type)) {
            $payPalGuestExpressCheckoutService = new PayPalGuestExpressCheckoutService();
            $payPalGuestExpressCheckoutService->startGuestPayPalPaymentTransaction($cart, $this->getOrderTotal($cart, $type), $data);
        }

        if (!$customer->id) {
            AdyenPrestaShopUtility::die400(['message' => 'Customer is undefined.']);
        }

        if (!empty($data['adyenBillingAddress'])) {
            $cart = $customerService->setCustomerAddresses($customer, $data, $cart);
        }

        if (count($cart->getAddressCollection()) === 0) {
            $this->handleNotSuccessfulPayment(self::FILE_NAME);
        }

        $currency = new PrestaCurrency($cart->id_currency);
        $amount = Amount::fromFloat(
            $this->getOrderTotal($cart, $type),
            Currency::fromIsoCode($currency->iso_code ?? 'EUR')
        );
        try {
            $response = CheckoutApi::get()->paymentRequest((string)$cart->id_shop)->startTransaction(
                new StartTransactionRequest(
                    $type,
                    $amount,
                    (string)$cart->id,
                    Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => $type]
                    ),
                    $additionalData,
                    [],
                    $this->getShopperReferenceFromCart($cart)
                )
            );

            if (!$response->isSuccessful()) {
                $this->handleNotSuccessfulPayment(self::FILE_NAME, $this->getUnsuccessfulUrl());
            }

            if (!$response->isAdditionalActionRequired()) {
                $this->handleSuccessfulPaymentWithoutAdditionalData($type, $cart, $amount);
            }

            $this->handleSuccessfulPaymentWithAdditionalData($response, $type, $cart, $amount);

            $this->context->smarty->assign(
                [
                    'action' => json_encode($response->getAction()),
                    'paymentRedirectActionURL' => Url::getFrontUrl(
                        'paymentredirect',
                        ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => $type]
                    ),
                    'checkoutConfigUrl' => Url::getFrontUrl('paymentconfig', ['cartId' => $cart->id]),
                    'checkoutUrl' => $this->context->link->getPageLink('order', $this->ssl, null)
                ]
            );
        } catch (Throwable $e) {
            Logger::logError(
                'Adyen failed to create order from Cart with ID: ' . $cart->id . ' Reason: ' . $e->getMessage()
            );
            $message = $this->module->l('Your payment could not be processed, please resubmit order.', self::FILE_NAME);
            $this->errors[] = $message;
            $this->redirectWithNotifications(Context::getContext()->link->getPageLink('order'));
        }
    }

    /**
     * @return string
     */
    private function getUnsuccessfulUrl(): string
    {
        $currentUrl = $_SERVER['HTTP_REFERER'];
        if (strpos($currentUrl, 'cart') !== false) {
            return Context::getContext()->link->getPageLink('cart') . '?action=show';
        }

        return '';
    }

    /**
     * @param Cart $cart
     *
     * @return ShopperReference|null
     */
    private function getShopperReferenceFromCart(Cart $cart): ?ShopperReference
    {
        $customer = new Customer($cart->id_customer);

        if (!$customer) {
            return null;
        }

        $shop = Shop::getShop(Context::getContext()->shop->id);

        return ShopperReference::parse($shop['domain'] . '_' . Context::getContext()->shop->id . '_' . $customer->id);
    }
}
