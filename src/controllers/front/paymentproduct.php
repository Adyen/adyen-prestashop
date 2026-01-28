<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PartialPayment\Request\StartPartialTransactionsRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\Services\Integration\CustomerService;
use AdyenPayment\Classes\Services\PayPalGuestExpressCheckoutService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Utility\SessionService;
use AdyenPayment\Classes\Utility\Url;
use AdyenPayment\Controllers\PaymentController;
use Currency as PrestaCurrency;

/**
 * Class AdyenOfficialPaymentProductModuleFrontController
 */
class AdyenOfficialPaymentProductModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPaymentProductModuleFrontController';

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

        if ($this->isHummigbirdTheme()) {
            $this->setTemplate('module:adyenofficial/views/templates/front/adyen-additional-details-hummingbird.tpl');
            return;
        }

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
        $additionalData = !empty(SessionService::get('stateData', false)) ?
            SessionService::get('stateData', true) : [];
        $data['adyen-additional-data'] = json_encode($additionalData);

        $type = !empty($additionalData['paymentMethod']['type']) ? $additionalData['paymentMethod']['type'] : '';

        $currencyId = (int)$this->context->currency->id;
        $langId = (int)$this->context->language->id;

        $cart = $this->context->cart;
        $products = $cart->getProducts();
        foreach ($products as $product) {
            $cart->deleteProduct(
                (int) $product['id_product'],
                (int) $product['id_product_attribute'] ?? 0,
                0, // id_customization
                (int) $product['id_address_delivery'] ?? 0
            );
        }
        $cart->save();
        $customer = $this->context->customer;

        /** @var CustomerService $customerService */
        $customerService = ServiceRegister::getService(CustomerService::class);
        $customerEmail = array_key_exists('adyenEmail', $data) ?
            str_replace(['"', "'"], '', $data['adyenEmail']) : '';
        if ($customerEmail !== '') {
            $customer = $customerService->createAndLoginCustomer($customerEmail, $data);
        } else {
            $this->context->updateCustomer($customer);
        }

        if (!$customer->id) {
            AdyenPrestaShopUtility::die400(['message' => 'Customer is undefined.']);
        }

        if (!empty($data['adyenBillingAddress'])) {
            $cart = $customerService->setCustomerAddresses($customer, $data, $cart);
        } else {
            $addresses = $customer->getAddresses($langId);
            if (count($addresses) > 0) {
                $cart = $this->updateCart($customer, $addresses[0]['id_address'], $addresses[0]['id_address'], $cart);
            }
        }

        $product = array_key_exists('product', $data) ? json_decode($data['product'], true) : [];
        $this->addProductToCart($cart, $product);

        if (PaymentMethodCode::payPal()->equals($type)) {
            $payPalGuestExpressCheckoutService = new PayPalGuestExpressCheckoutService();
            $payPalGuestExpressCheckoutService
                ->startGuestPayPalPaymentTransaction($cart, $this->getOrderTotal($cart, $type), $data);
        }

        $currency = new PrestaCurrency($currencyId);

        try {
            $partialTransactionsResponse = CheckoutApi::get()->partialPaymentRequest((string)$cart->id_shop)
                ->startPartialTransactions(
                    new StartPartialTransactionsRequest(
                        (string)$cart->id,
                        Currency::fromIsoCode($currency->iso_code ?? 'EUR'),
                        Url::getFrontUrl(
                            'paymentredirect',
                            ['adyenMerchantReference' => $cart->id, 'adyenPaymentType' => $type]
                        ),
                        $this->getOrderTotal($cart, $type),
                        $type,
                        [],
                        $additionalData,
                        [],
                        $this->getShopperReferenceFromCart($cart)
                    )
                );

            if (!$partialTransactionsResponse->isSuccessful()) {
                $product = new \Product($product['id_product'] ?? 0);
                $this->handleNotSuccessfulPayment(self::FILE_NAME, $product->getLink());
            }

            $amount = Amount::fromFloat(
                $this->getOrderTotal($cart, $type),
                Currency::fromIsoCode($currency->iso_code ?? 'EUR')
            );

            if (!$partialTransactionsResponse->isAdditionalActionRequired()) {
                $this->handleSuccessfulPaymentWithoutAdditionalData($type, $cart, $amount);
            }

            $this->handleSuccessfulPaymentWithAdditionalData(
                $partialTransactionsResponse->getLatestTransactionResponse(),
                $type,
                $cart,
                $amount
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
     * @param string $fileName
     * @param int $productId
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function redirectBack(string $fileName, int $productId): void
    {
        $message = $this->module->l('Your payment could not be processed, please resubmit order.', $fileName);
        SessionService::set(
            'errorMessage',
            $message
        );
        $product = new \Product($productId);

        if ($this->isAjaxRequest()) {
            die(
            json_encode(
                [
                    'nextStepUrl' => $product->getLink()
                ]
            )
            );
        }

        Tools::redirect($product->getLink());
    }


    /**
     * @param Cart $cart
     * @param $product
     *
     * @return void
     */
    private function addProductToCart(Cart $cart, $product)
    {
        $productId = array_key_exists('id_product', $product) ? (int)$product['id_product'] : 0;
        $idProductAttribute = array_key_exists('id_product', $product) ? (int)$product['id_product_attribute'] : 0;
        $quantityWanted = array_key_exists('id_product', $product) ? (int)$product['quantity_wanted'] : 0;
        $customizationId = array_key_exists('id_product', $product) ? (int)$product['id_customization'] : 0;

        if ($quantityWanted === 0) {
            $quantityWanted = 1;
        }

        $cart->updateQty($quantityWanted, $productId, $idProductAttribute, $customizationId);

        $cart->getPackageList(true);
        $cart->clearCache(true);
        $cart->update();
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
        $cart->id_carrier = CheckoutHandler::getCarrierId($cart);
        $cart->update();

        return $cart;
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
