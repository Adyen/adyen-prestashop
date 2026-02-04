<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperName;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperNameProcessor as PaymentLinkShopperNameProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperNameProcessor as ShopperNameProcessorInterface;

/**
 * Class ShopperNameProcessor
 */
class ShopperNameProcessor implements ShopperNameProcessorInterface, PaymentLinkShopperNameProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        if ($shopperName = $this->getCustomersNameFromCart((int) $context->getReference())) {
            $builder->setShopperName($shopperName);
        }
    }

    /**
     * @param PaymentLinkRequestBuilder $builder
     * @param PaymentLinkRequestContext $context
     *
     * @return void
     */
    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        if ($shopperName = $this->getCustomersNameFromCart((int) $context->getReference())) {
            $builder->setShopperName($shopperName);
        }
    }

    /**
     * @param int $cartId
     *
     * @return ShopperName|null
     */
    private function getCustomersNameFromCart(int $cartId): ?ShopperName
    {
        $cart = new \Cart($cartId);
        $customer = new \Customer($cart->id_customer);

        if (!$customer) {
            return null;
        }

        return new ShopperName(
            $customer->firstname ?? '',
            $customer->lastname ?? ''
        );
    }
}
