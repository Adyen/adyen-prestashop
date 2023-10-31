<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperReferenceProcessor as ShopperReferenceProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperReferenceProcessor as PaymentLinkShopperReferenceProcessorInterface;
use Cart;
use Context;
use Customer;
use Shop;

/**
 * Class ShopperReferenceProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperReferenceProcessor implements ShopperReferenceProcessorInterface,
                                           PaymentLinkShopperReferenceProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        if ($shopperReference = $this->getShopperRefferenceFromCart($context->getReference())) {
            $builder->setShopperReference($shopperReference);
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
        if ($shopperReference = $this->getShopperRefferenceFromCart($context->getReference())) {
            $builder->setShopperReference($shopperReference);
        }
    }

    /**
     * @param Cart $cart
     *
     * @return ShopperReference|null
     */
    private function getShopperRefferenceFromCart(int $cartId): ?ShopperReference
    {
        $cart = new Cart($cartId);
        $customer = new Customer($cart->id_customer);

        if (!$customer) {
            return null;
        }

        $shop = Shop::getShop(Context::getContext()->shop->id);

        return ShopperReference::parse(
            $shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . $customer->id
        );
    }
}
