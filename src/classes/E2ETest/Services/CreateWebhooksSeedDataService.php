<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\AdminAPI\OrderMappings\Request\OrderMappingsRequest;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\InvalidCaptureDelayException;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\InvalidCaptureTypeException;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Exceptions\InvalidRetentionPeriodException;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Models\CaptureType;
use Adyen\Core\BusinessLogic\Domain\Webhook\Repositories\WebhookConfigRepository;
use Adyen\Core\BusinessLogic\E2ETest\Services\CreateIntegrationDataService;
use Adyen\Core\Infrastructure\Http\Exceptions\HttpRequestException;
use Adyen\Core\Infrastructure\ServiceRegister;
use Adyen\Webhook\PaymentStates;
use AdyenPayment\Classes\E2ETest\Http\CartTestProxy;
use AdyenPayment\Classes\E2ETest\Http\OrderTestProxy;
use AdyenPayment\Classes\E2ETest\Http\ProductTestProxy;
use AdyenPayment\Classes\Services\AdyenOrderStatusMapping;
use Cart;
use Shop;

/**
 * Class CreateWebhooksSeedDataService
 */
class CreateWebhooksSeedDataService extends BaseCreateSeedDataService
{
    /**
     * Returns webhook config data from plugin database
     *
     * @return array
     *
     * @throws HttpRequestException
     */
    public function getWebhookAuthorizationData(): array
    {
        return $this->getCreateIntegrationDataService()->getWebhookAuthorizationData();
    }

    /**
     * Creates OrdersMappingConfiguration and orders for created existing customer
     *
     * @param string $customerId
     *
     * @return array
     *
     * @throws HttpRequestException
     * @throws InvalidCaptureDelayException
     * @throws InvalidCaptureTypeException
     * @throws InvalidRetentionPeriodException
     */
    public function createWebhookSeedData(string $customerId): array
    {
        $this->getCreateIntegrationDataService()->createGeneralSettingsConfiguration();
        $this->createOrdersMappingConfiguration();

        return $this->createOrders($customerId);
    }

    /**
     * Creates OrdersMappingConfiguration in database
     *
     * @return void
     */
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
     * Creates orders, transaction history details and returns array of merchant references and order amounts per order
     *
     * @param string $customerId
     *
     * @return array
     *
     * @throws HttpRequestException
     * @throws \PrestaShopException
     * @throws \Exception
     */
    private function createOrders(string $customerId): array
    {
        $ordersMerchantReferenceAndAmount = [];
        $addressId = \Address::getFirstCustomerAddressId($customerId);
        $orders = $this->readFromJSONFile()['orders'] ?? [];
        foreach ($orders as $order) {
            $currencyId = \Currency::getIdByIsoCode($order['currencyIsoCode']);
            $cartData = $this->createCart($customerId, $addressId, $currencyId, $order['orderItems']);
            \Shop::setContext(1, 1);
            $cartId = $cartData['cart']['id'];
            $cart = new \Cart($cartId);
            $totalAmount = $cart->getOrderTotal();
            $this->createOrderAndUpdateState($cartData, $totalAmount);
            $totalAmount = round($totalAmount, 2);
            $this->getCreateIntegrationDataService()->createTransactionHistoryForOrder(
                $cartId,
                $totalAmount,
                $order['currencyIsoCode'],
                $this->getCaptureType($order['captureType'])
            );
            $ordersMerchantReferenceAndAmount[$order['name']] = [
                'merchantReference' => $cartId,
                'amount' => $totalAmount * 100,
            ];
        }

        return $ordersMerchantReferenceAndAmount;
    }

    /**
     * Returns capture type for specific order
     *
     * @param string $captureTypeData
     *
     * @return CaptureType
     */
    private function getCaptureType(string $captureTypeData): CaptureType
    {
        if ($captureTypeData === 'manual') {
            return CaptureType::manual();
        }

        if ($captureTypeData === 'immediate') {
            return CaptureType::immediate();
        }

        return CaptureType::delayed();
    }

    /**
     * Creates order
     *
     * @param array $cartData
     * @param string $totalAmount
     *
     * @return void
     *
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
                $totalAmount,
            ],
            $data
        );

        $order = $this->getOrderTestProxy()->createOrder(['data' => $data])['order'];

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

        $this->getOrderTestProxy()->updateOrder(['data' => $data])['order'];
    }

    /**
     * Creates cart in shop
     *
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
     * Creates cart in shop with one item
     *
     * @param array $orderItem
     * @param int $customerId
     * @param int $addressId
     * @param int $currencyId
     *
     * @return array
     *
     * @throws HttpRequestException
     */
    private function createCartDataWithOneProduct(
        array $orderItem,
        int $customerId,
        int $addressId,
        int $currencyId,
    ): array {
        $productId = $orderItem['productId'];
        $productData = $this->getProductTestProxy()->getProductData($productId)['product'];
        $productAttributeId = $productData['cache_default_attribute'];
        $quantity = $orderItem['quantity'];
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
                '{quantity}',
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
                $quantity,
            ],
            $data
        );

        return $this->getCartTestProxy()->createCart(['data' => $data]);
    }

    /**
     * Creates cart in shop with two items
     *
     * @param array $orderItems
     * @param int $customerId
     * @param int $addressId
     * @param int $currencyId
     *
     * @return array
     *
     * @throws HttpRequestException
     */
    private function createCartDataWithTwoProducts(
        array $orderItems,
        int $customerId,
        int $addressId,
        int $currencyId,
    ): array {
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
                '{id_carrier}',
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
        for ($i = 1; $i <= $countOfOrderItems; ++$i) {
            $productId = $orderItems[$i - 1]['productId'];
            $productData = $this->getProductTestProxy()->getProductData($productId)['product'];
            $productAttributeId = $productData['cache_default_attribute'];
            $quantity = $orderItems[$i - 1]['quantity'];
            $data = str_replace(
                [
                    "{id_product_$i}",
                    "{id_product_attribute_$i}",
                    "{quantity_$i}",
                ],
                [
                    $productId,
                    $productAttributeId,
                    $quantity,
                ],
                $data
            );
        }

        return $this->getCartTestProxy()->createCart(['data' => $data]);
    }

    /**
     * @return WebhookConfigRepository
     */
    private function getWebhookConfigRepository(): WebhookConfigRepository
    {
        return ServiceRegister::getService(WebhookConfigRepository::class);
    }

    /**
     * @return OrderTestProxy
     */
    private function getOrderTestProxy(): OrderTestProxy
    {
        return ServiceRegister::getService(OrderTestProxy::class);
    }

    /**
     * @return ProductTestProxy
     */
    private function getProductTestProxy(): ProductTestProxy
    {
        return ServiceRegister::getService(ProductTestProxy::class);
    }

    /**
     * @return CartTestProxy
     */
    private function getCartTestProxy(): CartTestProxy
    {
        return ServiceRegister::getService(CartTestProxy::class);
    }

    /**
     * Returns CreateIntegrationDataService instance
     *
     * @return CreateIntegrationDataService
     */
    private function getCreateIntegrationDataService(): CreateIntegrationDataService
    {
        return ServiceRegister::getService(CreateIntegrationDataService::class);
    }
}
