<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\AdditionalData\AdditionalData;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\AdditionalData\BasketItem;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\AdditionalData\RiskData;
use Adyen\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\BasketItemsProcessor as BasketItemsProcessorInterface;

/**
 * Class BasketItemsProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class BasketItemsProcessor implements BasketItemsProcessorInterface
{
    /**
     * @var GeneralSettingsService
     */
    private $generalSettingsService;

    public function __construct(GeneralSettingsService $generalSettingsService)
    {
        $this->generalSettingsService = $generalSettingsService;
    }

    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $generalSettings = $this->generalSettingsService->getGeneralSettings();
        $cart = new \Cart($context->getReference());

        $additionalData = new AdditionalData(
            ($generalSettings && $generalSettings->isBasketItemSync())
                ? new RiskData($this->getItems($cart)) : null
        );

        $builder->setAdditionalData($additionalData);
    }

    /**
     * @param \Cart $cart
     * @return BasketItem[]
     */
    private function getItems(\Cart $cart): array
    {
        $basketContent = $cart->getProducts();
        $items = [];

        foreach ($basketContent as $item) {
            $items[] = new BasketItem(
                $item['id_product'] ?? '',
                '',
                $item['unit_price'] ?? '',
                $item['category'] ?? '',
                '',
                $cart->id_currency ? $this->getCurrency($cart->id_currency) : '',
                '',
                $item['name'] ?? '',
                $item['quantity'] ?? '',
                $cart->id_customer ? $this->getCustomerEmail($cart->id_customer) : '',
                '',
                '',
                $item['upc'] ?? ''
            );
        }

        return $items;
    }

    /**
     * @param int $id
     *
     * @return string|string[]
     */
    private function getCurrency(int $id)
    {
        return (new \Currency($id))->iso_code;
    }

    /**
     * @param int $id
     *
     * @return string
     */
    private function getCustomerEmail(int $id)
    {
        return (new \Customer($id))->email;
    }
}
