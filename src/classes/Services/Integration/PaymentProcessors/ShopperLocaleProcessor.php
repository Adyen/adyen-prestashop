<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperLocaleProcessor as ShopperLocaleProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperLocaleProcessor as PaymentLinkShopperLocaleProcessorInterface;
use Context;

/**
 * Class ShopperLocaleProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperLocaleProcessor implements ShopperLocaleProcessorInterface, PaymentLinkShopperLocaleProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $builder->setShopperLocale($this->getShopperLocale());
    }

    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        $builder->setShopperLocale($this->getShopperLocale());
    }

    /**
     * @return string
     */
    private function getShopperLocale(): string
    {
        return str_replace('-', '_', Context::getContext()->language->locale);
    }
}
