<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionHistoryService;
use Adyen\Core\Infrastructure\Logger\LogContextData;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\Integration\CustomerService;
use AdyenPayment\Classes\Utility\SessionService;
use AdyenPayment\Controllers\PaymentController;

/**
 * Class AdyenOfficialPaymentRedirectModuleFrontController
 */
class AdyenOfficialPaymentRedirectModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    public const FILE_NAME = 'AdyenOfficialPaymentRedirectModuleFrontController';

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
            [new LogContextData('request', json_encode($requestData))]
        );

        $cart = $this->getCurrentCart();
        $page = Tools::getValue('adyenPage');

        if (!Validate::isLoadedObject($cart)) {
            Tools::redirect($this->context->link->getPageLink('order', $this->ssl));
        }

        $customerService = new CustomerService();
        $customer = new Customer($this->context->customer->id);

        $isGuestCheckout = false;
        if (empty($this->context->customer->id) && !empty($requestData['adyenEmail'])) {
            $customerService->createAndLoginCustomer($requestData['adyenEmail'], $requestData);
            $customer = new Customer($this->context->customer->id);
            $isGuestCheckout = true;
        }

        if (!$isGuestCheckout) {
            $customer = new Customer($cart->id_customer);
        }

        if (!empty($requestData['adyenBillingAddress']) && !empty($requestData['adyenShippingAddress'])) {
            list($shippingAddressId, $billingAddressId) = $customerService->saveAddresses($customer, $requestData);
            $cart = $this->updateCart($billingAddressId, $shippingAddressId, $cart);
        }

        $response = CheckoutAPI::get()
            ->paymentRequest((string) $cart->id_shop)
            ->updatePaymentDetails(
                array_key_exists('details', $requestData) ? $requestData : ['details' => $requestData]);

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
            if ($isGuestCheckout) {
                $cart->secure_key = $customer->secure_key;
                $cart->id_customer = $customer->id;
                $cart->update();
            }

            /** @var TransactionHistoryService $transactionService */
            $transactionService = ServiceRegister::getService(TransactionHistoryService::class);
            $transactionHistory = Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext::doWithStore(
                (string) $cart->id_shop,
                static function () use ($cart, $transactionService) {
                    return $transactionService->getTransactionHistory((string) $cart->id);
                }
            );
            $currency = new Currency($cart->id_currency);
            $payments = $transactionHistory->collection()->filterAllByEventCode('PAYMENT_REQUESTED')
                ->getAmount(
                    Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode($currency->iso_code)
                );
            SessionService::get('giftCardsData');
            $this->saveOrder(Tools::getValue('adyenPaymentType'), $cart, $payments);

            if (isset($requestData['details'])) {
                exit(json_encode(['nextStepUrl' => $this->generateSuccessURL($cart)]));
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
     * @param int $deliveryAddressId
     * @param int $invoiceAddressId
     * @param Cart $cart
     *
     * @return Cart
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function updateCart(int $deliveryAddressId, int $invoiceAddressId, Cart $cart): Cart
    {
        $cart->id_address_delivery = $deliveryAddressId;
        $cart->id_address_invoice = $invoiceAddressId;
        $cart->update();

        return $cart;
    }
}
