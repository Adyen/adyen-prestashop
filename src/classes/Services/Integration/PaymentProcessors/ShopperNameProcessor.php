<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperName;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperNameProcessor as ShopperNameProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperNameProcessor as PaymentLinkShopperNameProcessorInterface;
use Cart;
use Customer;

/**
 * Class ShopperNameProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
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
        $cart = new Cart($context->getReference());
        $shopperName = $this->getCustomersNameFromCart($cart);

        if ($shopperName) {
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
        $cart = new Cart($context->getReference());
        $shopperName = $this->getCustomersNameFromCart($cart);

        if ($shopperName) {
            $builder->setShopperName($shopperName);
        }
    }

    /**
     * @param Cart $cart
     *
     * @return ShopperName|null
     */
    private function getCustomersNameFromCart(Cart $cart): ?ShopperName
    {
        $customer = new Customer($cart->id_customer);

        if (!$customer) {

            return null;
        }

        return new ShopperName(
            $customer->firstname ?? '',
            $customer->lastname ?? ''
        );
    }
}
