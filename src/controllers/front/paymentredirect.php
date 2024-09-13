<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\Infrastructure\Logger\Logger;
use AdyenPayment\Classes\Bootstrap;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Services\Integration\CustomerService;
use AdyenPayment\Controllers\PaymentController;

/**
 * Class AdyenOfficialPaymentRedirectModuleFrontController
 */
class AdyenOfficialPaymentRedirectModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPaymentRedirectModuleFrontController';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function postProcess()
    {
        $requestData = Tools::getAllValues();

        Logger::logDebug(
            'Received handleRedirectAction request',
            'Integration',
            ['request' => json_encode($requestData)]
        );

        $cart = $this->getCurrentCart();
        $page = Tools::getValue('adyenPage');

        if (!\Validate::isLoadedObject($cart)) {
            Tools::redirect($this->context->link->getPageLink('order', $this->ssl));
        }

        $customerService = new CustomerService();

        if (empty($this->context->customer->id) && !empty($requestData['adyenEmail'])) {
            $customerService->createAndLoginCustomer($requestData['adyenEmail'], $requestData);
        }

        $customer = new Customer($this->context->customer->id);
        if (!empty($requestData['adyenBillingAddress']) && !empty($requestData['adyenShippingAddress'])) {
            list($shippingAddressId, $billingAddressId) = $customerService->saveAddresses($customer, $requestData);
            $cart = $this->updateCart($customer, $billingAddressId, $shippingAddressId, $cart);
        }

        $response = CheckoutAPI::get()
            ->paymentRequest((string)$cart->id_shop)
            ->updatePaymentDetails(array_key_exists('details', $requestData) ? $requestData : ['details' => $requestData]);

        if (!$response->isSuccessful() && $page !== 'thankYou') {
            $message = $this->module->l('Your payment could not be processed, please resubmit order.', self::FILE_NAME);
            $this->errors[] = $message;
            $this->redirectWithNotifications(
                Context::getContext()->link->getPageLink('order')
            );
        }

        if (!$response->isSuccessful() && $page === 'thankYou') {
            return;
        }

        try {
            $customer = new Customer($this->context->customer->id);

            $cart->secure_key = $customer->secure_key;
            $cart->id_customer = $customer->id;
            $cart->update();

            $this->saveOrder(Tools::getValue('adyenPaymentType'), $cart);

            if (isset($requestData['details'])) {
                die(json_encode(['nextStepUrl' => $this->generateSuccessURL($cart)]));
            }

            Tools::redirect($this->generateSuccessURL($cart));
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
     * @return Cart
     */
    protected function getCurrentCart(): Cart
    {
        return new Cart(Tools::getValue('adyenMerchantReference'));
    }

    /**
     * @param Customer $customer
     * @param int $deliveryAddressId
     * @param int $invoiceAddressId
     * @param Cart $cart
     *
     * @return Cart
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function updateCart(Customer $customer, int $deliveryAddressId, int $invoiceAddressId, Cart $cart): Cart
    {
        $cart->secure_key = $customer->secure_key;
        $cart->id_address_delivery = $deliveryAddressId;
        $cart->id_address_invoice = $invoiceAddressId;
        $cart->id_customer = $customer->id;
        $cart->update();

        return $cart;
    }
}
