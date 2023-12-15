<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Address;
use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\OrderMappings\Request\OrderMappingsRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\DataBag;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Models\CaptureType;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Services\TransactionHistoryService;
use Adyen\Core\BusinessLogic\Domain\Webhook\Repositories\WebhookConfigRepository;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Webhook\PaymentStates;
use AdyenPayment\Classes\E2ETest\Http\CartTestProxy;
use AdyenPayment\Classes\E2ETest\Http\OrderTestProxy;
use AdyenPayment\Classes\E2ETest\Http\ProductTestProxy;
use AdyenPayment\Classes\Services\AdyenOrderStatusMapping;
use Cart;
use Currency;
use Exception;
use Shop;

/**
 * Class CreateWebhooksSeedDataService
 *
 * @package AdyenPayment\E2ETest\Services
 */
class CreateWebhooksSeedDataService extends BaseCreateSeedDataService
{
    /**
     * @var CartTestProxy
     */
    private $cartTestProxy;
    /**
     * @var ProductTestProxy
     */
    private $productTestProxy;
    /**
     * @var OrderTestProxy
     */
    private $orderTestProxy;

    public function __construct(
        CartTestProxy    $cartTestProxy,
        ProductTestProxy $productTestProxy,
        OrderTestProxy   $orderTestProxy)
    {
        $this->cartTestProxy = $cartTestProxy;
        $this->productTestProxy = $productTestProxy;
        $this->orderTestProxy = $orderTestProxy;
    }

    /**
     * @throws Exception
     */
    public function getWebhookAuthorizationData(): array
    {
        $webhookConfig = StoreContext::doWithStore(1, function () {
            return $this->getWebhookConfigRepository()->getWebhookConfig();
        });

        $authData = [];
        if ($webhookConfig) {
            $authData['username'] = $webhookConfig->getUsername();
            $authData['password'] = $webhookConfig->getPassword();
            $authData['hmac'] = $webhookConfig->getHmac();
        }

        return $authData;
    }

    /**
     * @throws HttpRequestException
     */
    public function createWebhookSeedData(string $customerId): array
    {
        $this->createOrdersMappingConfiguration();
        return $this->createOrders($customerId);
    }

    private function createOrdersMappingConfiguration(): void
    {
        $defaultOrdersStatusMap = AdyenOrderStatusMapping::getDefaultOrderStatusMap();
        $orderStatusMapData['inProgress'] = $defaultOrdersStatusMap[PaymentStates::STATE_IN_PROGRESS];
        $orderStatusMapData['pending'] = $defaultOrdersStatusMap[PaymentStates::STATE_PENDING];
        $orderStatusMapData['paid'] = $defaultOrdersStatusMap[PaymentStates::STATE_PAID];
        $orderStatusMapData['failed'] = $defaultOrdersStatusMap[PaymentStates::STATE_FAILED];
        $orderStatusMapData['refunded'] = $defaultOrdersStatusMap[PaymentStates::STATE_REFUNDED];
        $orderStatusMapData['partiallyRefunded'] = $defaultOrdersStatusMap[PaymentStates::STATE_PARTIALLY_REFUNDED];
        $orderStatusMapData['cancelled'] = $defaultOrdersStatusMap[PaymentStates::STATE_CANCELLED];
        $orderStatusMapData['new'] = $defaultOrdersStatusMap[PaymentStates::STATE_NEW];
        $orderStatusMapData['chargeBack'] = $defaultOrdersStatusMap[PaymentStates::CHARGE_BACK];

        $orderStatusMapRequest = OrderMappingsRequest::parse($orderStatusMapData);

        AdminAPI::get()->orderMappings(1)->saveOrderStatusMap($orderStatusMapRequest);
    }

    /**
     * @throws HttpRequestException
     * @throws Exception
     */
    private function createOrders(string $customerId): array
    {
        $ordersMerchantReferenceAndAmount = [];
        $index = 1;
        $addressId = Address::getFirstCustomerAddressId($customerId);
        $orders = $this->readFromJSONFile()['orders'] ?? [];
        foreach ($orders as $order) {
            $currencyId = Currency::getIdByIsoCode($order['currencyIsoCode']);
            $cartData = $this->createCart($customerId, $addressId, $currencyId, $order['orderItems']);
            Shop::setContext(1, 1);
            $cartId = $cartData['cart']['id'];
            $cart = new Cart($cartId);
            $totalAmount = $cart->getOrderTotal();
            $this->createOrderAndUpdateState($cartData, $totalAmount);
            StoreContext::doWithStore('1', function () use ($cartId, $totalAmount, $order) {
                $transactionContext = new StartTransactionRequestContext(
                    PaymentMethodCode::parse('scheme'),
                    Amount::fromFloat(
                        $totalAmount,
                        \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                            $order['currencyIsoCode']
                        )
                    ),
                    $cartId,
                    '',
                    new DataBag([]),
                    new DataBag([])
                );
                /** @var TransactionHistoryService $transactionHistoryService */
                $transactionHistoryService = ServiceRegister::getService(TransactionHistoryService::class);
                $transactionHistoryService->createTransactionHistory($transactionContext->getReference(),
                    $transactionContext->getAmount()->getCurrency(),
                    $this->getCaptureType($order['captureType'])
                );
            });

            $ordersMerchantReferenceAndAmount['order_' . $index] = [
                'merchantReference' => $cartId,
                'amount' => $totalAmount * 100
            ];
            $index++;
        }

        return $ordersMerchantReferenceAndAmount;
    }

    private function getCaptureType(string $captureTypeData): CaptureType
    {
        if ($captureTypeData === 'manual'){
            return CaptureType::manual();
        }

        if ($captureTypeData === 'immediate'){
            return CaptureType::immediate();
        }

        return CaptureType::delayed();
    }

    /**
     * Creates order
     *
     * @param array $cartData
     * @param string $totalAmount
     * @return void
     * @throws HttpRequestException
     */
    private function createOrderAndUpdateState(array $cartData, string $totalAmount): void
    {
        $cart = $cartData['cart'];
        $data = $this->readFomXMLFile('create_order');
        $data = str_replace(
            [
                '{id_address_delivery}',
                '{id_address_invoice}',
                '{id_cart}',
                '{id_currency}',
                '{id_lang}',
                '{id_customer}',
                '{id_carrier}',
                '{current_state}',
                '{id_shop_group}',
                '{id_shop}',
                '{total_paid}',
            ],
            [
                $cart['id_address_delivery'],
                $cart['id_address_invoice'],
                $cart['id'],
                $cart['id_currency'],
                $cart['id_lang'],
                $cart['id_customer'],
                $cart['id_carrier'],
                AdminAPI::get()->orderMappings($cart['id_shop'])->getOrderStatusMap()->toArray()['inProgress'],
                $cart['id_shop_group'],
                $cart['id_shop'],
                $totalAmount
            ],
            $data
        );

        $order = $this->orderTestProxy->createOrder(['data' => $data])['order'];

        $data = $this->readFomXMLFile('update_order');
        $data = str_replace(
            [
                '{id}',
                '{id_address_delivery}',
                '{id_address_invoice}',
                '{id_cart}',
                '{id_currency}',
                '{id_lang}',
                '{id_customer}',
                '{id_carrier}',
                '{current_state}',
                '{id_shop_group}',
                '{id_shop}',
                '{total_paid}',
                '{reference}',
            ],
            [
                $order['id'],
                $cart['id_address_delivery'],
                $cart['id_address_invoice'],
                $cart['id'],
                $cart['id_currency'],
                $cart['id_lang'],
                $cart['id_customer'],
                $cart['id_carrier'],
                AdminAPI::get()->orderMappings($cart['id_shop'])->getOrderStatusMap()->toArray()['inProgress'],
                $cart['id_shop_group'],
                $cart['id_shop'],
                $totalAmount,
                $order['reference'],
            ],
            $data
        );

        $this->orderTestProxy->updateOrder(['data' => $data])['order'];
    }

    /**
     * @throws HttpRequestException
     */
    private function createCart(int $customerId, int $addressId, int $currencyId, array $orderItems): array
    {
        if (count($orderItems) === 1) {
            return $this->createCartDataWithOneProduct($orderItems[0], $customerId, $addressId, $currencyId);
        }

        return $this->createCartDataWithTwoProducts($orderItems, $customerId, $addressId, $currencyId);
    }

    /**
     * @param array $orderItem
     * @param int $customerId
     * @param int $addressId
     * @param int $currencyId
     * @return array
     * @throws HttpRequestException
     */
    private function createCartDataWithOneProduct(
        array $orderItem,
        int   $customerId,
        int   $addressId,
        int   $currencyId
    ): array
    {
        $productId = $orderItem["productId"];
        $productData = $this->productTestProxy->getProductData($productId)['product'];
        $productAttributeId = $productData['cache_default_attribute'];
        $quantity = $orderItem["quantity"];
        $data = $this->readFomXMLFile('create_cart_one_product');
        $data = str_replace(
            [
                '{id_address_delivery}',
                '{id_address_invoice}',
                '{id_currency}',
                '{id_customer}',
                '{id_lang}',
                '{id_shop_group}',
                '{id_shop}',
                '{id_carrier}',
                '{id_product}',
                '{id_product_attribute}',
                '{quantity}'
            ],
            [
                $addressId,
                $addressId,
                $currencyId,
                $customerId,
                1,
                1,
                1,
                1,
                $productId,
                $productAttributeId,
                $quantity
            ],
            $data
        );

        return $this->cartTestProxy->createCart(['data' => $data]);
    }

    /**
     * @param array $orderItems
     * @param int $customerId
     * @param int $addressId
     * @param int $currencyId
     * @return array
     * @throws HttpRequestException
     */
    private function createCartDataWithTwoProducts(
        array $orderItems,
        int   $customerId,
        int   $addressId,
        int   $currencyId
    ): array
    {
        $data = $this->readFomXMLFile('create_cart_two_products');
        $data = str_replace(
            [
                '{id_address_delivery}',
                '{id_address_invoice}',
                '{id_currency}',
                '{id_customer}',
                '{id_lang}',
                '{id_shop_group}',
                '{id_shop}',
                '{id_carrier}'
            ],
            [
                $addressId,
                $addressId,
                $currencyId,
                $customerId,
                1,
                1,
                1,
                1,
            ],
            $data
        );

        $countOfOrderItems = count($orderItems);
        for ($i = 1; $i <= $countOfOrderItems; $i++) {
            $productId = $orderItems[$i - 1]["productId"];
            $productData = $this->productTestProxy->getProductData($productId)['product'];
            $productAttributeId = $productData['cache_default_attribute'];
            $quantity = $orderItems[$i - 1]["quantity"];
            $data = str_replace(
                [
                    "{id_product_$i}",
                    "{id_product_attribute_$i}",
                    "{quantity_$i}"
                ],
                [
                    $productId,
                    $productAttributeId,
                    $quantity
                ],
                $data
            );
        }

        return $this->cartTestProxy->createCart(['data' => $data]);
    }

    /**
     * Returns WebhookConfigRepository instance
     *
     * @return WebhookConfigRepository
     */
    private function getWebhookConfigRepository(): WebhookConfigRepository
    {
        return ServiceRegister::getService(WebhookConfigRepository::class);
    }
}