<?php

namespace AdyenPayment\Controllers;

use Address;
use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\DataAccess\Payment\Exceptions\PaymentMethodNotConfiguredException;
use Adyen\Core\BusinessLogic\AdminAPI\Response\Response;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodCode;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\MethodAdditionalData\Oney;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\PaymentMethod;
use Adyen\Core\BusinessLogic\Domain\Payment\Services\PaymentService;
use Adyen\Core\BusinessLogic\Domain\Translations\Model\TranslatableLabel;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Repositories\ProductRepository;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\SurchargeCalculator;
use AdyenPayment\Classes\Utility\SessionService;
use AdyenPayment\Classes\Utility\Url;
use Carrier;
use Cart;
use Context;
use CurrencyCore;
use Db;
use Exception;
use Order;
use OrderHistory;
use PrestaShopDatabaseException;
use PrestaShopException;
use Product;
use StockAvailable;
use Tools;

class PaymentController extends \ModuleFrontController
{
    /**
     * @param string $paymentMethodCode
     * @param Cart $cart
     * @param Amount $amount
     *
     * @return void
     *
     * @throws Exception
     */
    protected function saveOrder(string $paymentMethodCode, Cart $cart, Amount $amount): void
    {
        StoreContext::doWithStore(
            (string)$cart->id_shop,
            function () use ($paymentMethodCode, $cart, $amount) {
                $this->doSaveOrder($paymentMethodCode, $cart, $amount);
            }
        );
    }

    /**
     * @param Cart $cart
     * @param string $paymentMethodType
     *
     * @return float|int
     *
     * @throws PaymentMethodNotConfiguredException
     * @throws Exception
     */
    protected function getOrderTotal(Cart $cart, string $paymentMethodType)
    {
        return StoreContext::doWithStore($cart->id_shop, function () use ($cart, $paymentMethodType) {
            $paymentMethod = $this->getPaymentMethod($paymentMethodType);

            $precision = _PS_PRICE_COMPUTE_PRECISION_;
            if (version_compare(_PS_VERSION_, '1.7.7.0', 'ge')) {
                $precision = Context::getContext()->getComputingPrecision();
            }

            if (!$paymentMethod) {
                throw new PaymentMethodNotConfiguredException(new TranslatableLabel('Method not configured.', ''));
            }
            $idCarrier = CheckoutHandler::getCarrierId($cart);
            $currency = new CurrencyCore($cart->id_currency);

            return $cart->getOrderTotal(true, Cart::BOTH, null, $idCarrier) + Tools::ps_round(
                    $this->convertPrice(
                        SurchargeCalculator::calculateSurcharge(
                            $paymentMethod,
                            $currency->conversion_rate,
                            Amount::fromFloat(
                                $cart->getOrderTotal(true, Cart::BOTH, null, $idCarrier),
                                Currency::fromIsoCode($currency->iso_code)
                            )
                        ),
                        $currency
                    ),
                    $precision
                );
        });
    }

    protected function convertPrice(float $price, CurrencyCore $currency): float
    {
        return Tools::convertPrice(Tools::convertPrice($price, $currency, false), $currency);
    }

    /**
     * @param string $fileName
     * @param string $url
     *
     * @return void
     */
    protected function handleNotSuccessfulPayment(string $fileName, string $url = '')
    {
        $message = $this->module->l('Your payment could not be processed, please resubmit order.', $fileName);

        if ($this->isAjaxRequest()) {
            SessionService::set(
                'errorMessage',
                $message
            );
            die(
            json_encode(
                [
                    'nextStepUrl' => $url
                ]
            )
            );
        }

        $this->context->controller->errors[] = $message;
        $this->redirectWithNotifications(
            Context::getContext()->link->getPageLink('order')
        );
    }

    /**
     * @param string $type
     * @param Cart $cart
     * @param Amount $amount
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws Exception
     */
    protected function handleSuccessfulPaymentWithoutAdditionalData(string $type, Cart $cart, Amount $amount)
    {
        $this->saveOrder($type, $cart, $amount);

        if ($this->isAjaxRequest()) {
            die(
            json_encode(
                [
                    'nextStepUrl' => $this->generateSuccessURL($cart)
                ]
            )
            );
        }
        Tools::redirect($this->generateSuccessURL($cart));
    }

    /**
     * @param Response $response
     * @param string $type
     * @param Cart $cart
     * @param Amount $amount
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws Exception
     */
    protected function handleSuccessfulPaymentWithAdditionalData(
        Response $response,
        string $type,
        Cart $cart,
        Amount $amount
    ) {
        if ($this->isAjaxRequest() && PaymentMethodCode::scheme()->equals($type)) {
            SessionService::set('cartId', $cart->id);
            SessionService::set('adyenAction', json_encode($response->getAction()));
            SessionService::set('adyenPaymentMethodType', $type);

            die(
            json_encode(
                [
                    'nextStepUrl' => Url::getFrontUrl('clicktopay')
                ]
            )
            );
        }

        if ($this->isAjaxRequest()) {
            die(
            json_encode(
                [
                    'action' => $response->getAction(),
                    'reference' => $cart->id
                ]
            )
            );
        }

        if ($response->shouldPresentToShopper() || $response->isRecieved()) {
            $this->saveOrder($type, $cart, $amount);

            SessionService::set('cartId', $cart->id);
            SessionService::set('adyenAction', json_encode($response->getAction()));
            SessionService::set('adyenPaymentMethodType', $type);
            Tools::redirect($this->generateSuccessURL($cart));
        }
    }

    /**
     * @return false|mixed
     */
    protected function isAjaxRequest()
    {
        return Tools::getValue('isXHR');
    }

    /**
     * Create URL to order-confirmation page.
     *
     * @param Cart $cart
     *
     * @return string
     */
    protected function generateSuccessURL(Cart $cart): string
    {
        return $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            [
                'id_cart' => (int)$cart->id,
                'id_module' => (int)$this->module->id,
                'id_order' => $this->module->currentOrder,
                'key' => $cart->secure_key
            ]
        );
    }

    /**
     * @param string $paymentMethodCode
     * @param Cart $cart
     * @param Amount $amount
     *
     * @return void
     *
     * @throws PaymentMethodNotConfiguredException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */
    private function doSaveOrder(string $paymentMethodCode, Cart $cart, Amount $amount)
    {
        $method = $this->getPaymentMethod($paymentMethodCode);

        if (empty($method)) {
            throw new PaymentMethodNotConfiguredException(new TranslatableLabel('Payment method not configured.', ''));
        }

        if ($cart->orderExists()) {
            return;
        }

        $idCarrier = CheckoutHandler::getCarrierId($cart);
        $total = $cart->getOrderTotal(true, Cart::BOTH, null, $idCarrier);
        $shouldCalculateSurcharge = $this->methodHasSurcharge($method);

        if ($shouldCalculateSurcharge) {
            $product = $this->createSurchargeProduct($method, $total, $cart->id_currency);
            $productId = $product->id;
            StockAvailable::setQuantity($productId, null, 1);
            $cart->updateQty(1, $productId);
            $cart->getPackageList(true);
            $cart->clearCache(true);
            $cart->update();
        }

        $inProgressPaymentId = AdminAPI::get()->orderMappings($cart->id_shop)
            ->getOrderStatusMap()->toArray()['inProgress'];

        // refresh delivery option list because of guest express checkout
        $cart->getDeliveryOptionList(new \Country($cart->id_address_delivery), true);

        $this->module->validateOrder(
            $cart->id,
            (int)$inProgressPaymentId,
            $amount->getPriceInCurrencyUnits(),
            $this->module->displayName,
            null,
            [],
            null,
            true,
            $cart->secure_key
        );

        $order = new Order($this->module->currentOrder);
        if ((int)$order->id_carrier !== (int)$cart->id_carrier) {
            $this->updateShippingCost($order, $cart->getPackageShippingCost(), $idCarrier, (int)$inProgressPaymentId);
        }

        if ($shouldCalculateSurcharge && $product->active) {
            $product->toggleStatus();
            $product->update();
        }
    }

    /**
     * @param Order $order
     * @param float $newCost
     * @param int $idCarrier
     * @param int $idOrderState
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function updateShippingCost(Order $order, float $newCost, int $idCarrier, int $idOrderState): void
    {
        $carrier = new Carrier($idCarrier);
        $taxRate = (float)$carrier->getTaxesRate(new Address($order->id_address_delivery));
        $order->id_carrier = $idCarrier;
        $order->total_paid = $order->total_paid - ($order->total_shipping - $newCost);
        $order->total_paid_tax_incl = $order->total_paid_tax_incl - ($order->total_shipping - $newCost);
        $order->total_paid_tax_excl = $order->total_paid_tax_excl - ($order->total_shipping - $newCost);
        $order->total_shipping_tax_incl = $newCost;
        $order->total_shipping_tax_excl = $taxRate > 0 ? round($newCost / (1 + $taxRate / 100.0), 2) : $newCost;
        $order->total_shipping = $newCost;
        $order->current_state = (int)$idOrderState;
        $order->update();

        Db::getInstance()->delete('order_history', 'id_order = ' . (int)$order->id);
        $history = new OrderHistory();
        $history->id_order = $order->id;
        $history->id_employee = "0";
        $history->changeIdOrderState($idOrderState, $order->id, true);
        $history->add();
    }

    /**
     * @param PaymentMethod $paymentMethod
     * @param float $amount
     * @param int $currencyId
     *
     * @return Product
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function createSurchargeProduct(PaymentMethod $paymentMethod, float $amount, int $currencyId): Product
    {
        $productId = $this->getProductRepository()->getProductIdByProductName($this->module->l('Surcharge'));
        $cartCurrency = new \Currency($currencyId);
        $precision = _PS_PRICE_COMPUTE_PRECISION_;
        if (version_compare(_PS_VERSION_, '1.7.7.0', 'ge')) {
            $precision = Context::getContext()->getComputingPrecision();
        }

        $price = Tools::ps_round(
            Tools::convertPrice(
                SurchargeCalculator::calculateSurcharge(
                    $paymentMethod,
                    $cartCurrency->getConversionRate(),
                    Amount::fromFloat(
                        $amount,
                        Currency::fromIsoCode($cartCurrency->iso_code)
                    )
                ),
                $cartCurrency,
                false
            ),
            $precision
        );

        if ($productId) {
            $product = new Product($productId);
            $product->name = $this->module->l('Surcharge');
            $product->price = $price;
            $product->id_tax_rules_group = 0;
            $product->clearCache();
            $product->update();

            return $product;
        }

        $product = new Product();
        $product->name = $this->module->l('Surcharge');;
        $product->price = $price;
        $product->id_tax_rules_group = 0;
        $product->add();

        return $product;
    }

    /**
     * @param PaymentMethod $method
     *
     * @return bool
     */
    private function methodHasSurcharge(PaymentMethod $method): bool
    {
        return $method->getSurchargeType() !== null && $method->getSurchargeType() !== 'none';
    }

    /**
     * @param string $paymentMethodType
     *
     * @return PaymentMethod|null
     *
     * @throws Exception
     */
    protected function getPaymentMethod(string $paymentMethodType): ?PaymentMethod
    {
        if (strpos($paymentMethodType, 'facilypay') !== false) {
            return $this->getOneyMethod($paymentMethodType);
        }

        if (in_array($paymentMethodType, ['googlepay', 'paywithgoogle'])) {
            return $this->getGooglePayMethod();
        }

        return $this->getPaymentService()->getPaymentMethodByCode($paymentMethodType);
    }

    /**
     * @return PaymentMethod
     *
     * @throws Exception
     */
    private function getGooglePayMethod(): PaymentMethod
    {
        $method = $this->getPaymentService()->getPaymentMethodByCode('googlepay');

        return $method ?: $this->getPaymentService()->getPaymentMethodByCode('paywithgoogle');
    }

    /**
     * @param string $paymentMethodType
     *
     * @return PaymentMethod|null
     *
     * @throws Exception
     */
    private function getOneyMethod(string $paymentMethodType): ?PaymentMethod
    {
        $oneyConfig = $this->getPaymentService()->getPaymentMethodByCode('oney');

        if (empty($oneyConfig)) {
            return null;
        }

        /** @var Oney $additionalData */
        $additionalData = $oneyConfig->getAdditionalData();

        foreach ($additionalData->getSupportedInstallments() as $installment) {
            if (strpos($paymentMethodType, $installment) !== false) {
                return $oneyConfig;
            }
        }

        return null;
    }

    /**
     * @return PaymentService
     */
    private function getPaymentService(): PaymentService
    {
        return ServiceRegister::getService(PaymentService::class);
    }

    /**
     * @return ProductRepository
     */
    private function getProductRepository(): ProductRepository
    {
        return new ProductRepository();
    }
}
