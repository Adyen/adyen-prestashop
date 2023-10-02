<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperName;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\ShopperNameProcessor as ShopperNameProcessorInterface;

/**
 * Class ShopperNameProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperNameProcessor implements ShopperNameProcessorInterface
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

        $shopperName = new ShopperName(
            $customer->firstname ?? '',
            $customer->lastname ?? ''
        );

        $builder->setShopperName($shopperName);
    }
}
