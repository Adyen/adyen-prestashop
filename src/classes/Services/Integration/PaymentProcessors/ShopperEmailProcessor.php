<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\ShopperEmailProcessor as ShopperEmailProcessorInterface;

/**
 * Class ShopperEmailProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperEmailProcessor implements ShopperEmailProcessorInterface
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

        if (!$customer || !isset($customer->email)) {
            return;
        }

        $builder->setShopperEmail($customer->email);
    }
}
