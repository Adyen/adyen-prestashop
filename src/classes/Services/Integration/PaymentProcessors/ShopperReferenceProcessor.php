<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\ShopperReferenceProcessor as ShopperReferenceProcessorInterface;

/**
 * Class ShopperReferenceProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperReferenceProcessor implements ShopperReferenceProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $cart = new \Cart($context->getReference());
        $customer = new \Customer($cart->id_customer);

        if (!$customer) {
            return;
        }

        $shop = \Shop::getShop(\Context::getContext()->shop->id);

        $builder->setShopperReference(
            ShopperReference::parse(
                $shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . $customer->id
            )
        );
    }
}
