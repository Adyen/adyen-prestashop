<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperReferenceProcessor as PaymentLinkShopperReferenceProcessorInterface;

/**
 * Class ShopperReferenceProcessor
 */
class ShopperReferenceProcessor implements PaymentLinkShopperReferenceProcessorInterface
{
    /**
     * @param PaymentLinkRequestBuilder $builder
     * @param PaymentLinkRequestContext $context
     *
     * @return void
     */
    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        if ($shopperReference = $this->getShopperReferenceFromCart((int) $context->getReference())) {
            $builder->setShopperReference($shopperReference);
        }
    }

    /**
     * @param int $cartId
     *
     * @return ShopperReference|null
     */
    private function getShopperReferenceFromCart(int $cartId): ?ShopperReference
    {
        $cart = new \Cart($cartId);
        $customer = new \Customer($cart->id_customer);

        if (!$customer) {
            return null;
        }

        $shop = \Shop::getShop(\Context::getContext()->shop->id);

        return ShopperReference::parse($shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . $customer->id);
    }
}
