<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\ShopperLocaleProcessor as ShopperLocaleProcessorInterface;

/**
 * Class ShopperLocaleProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperLocaleProcessor implements ShopperLocaleProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $builder->setShopperLocale(str_replace('-', '_', \Context::getContext()->language->locale));
    }
}
