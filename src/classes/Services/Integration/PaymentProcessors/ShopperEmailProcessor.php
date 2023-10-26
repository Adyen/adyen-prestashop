<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ShopperEmailProcessor as ShopperEmailProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperEmailProcessor as PaymentLinkShopperEmailProcessorInterface;
use Cart;
use Customer;

/**
 * Class ShopperEmailProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class ShopperEmailProcessor implements ShopperEmailProcessorInterface, PaymentLinkShopperEmailProcessorInterface
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
        $email = $this->getCustomersEmailFromCart($cart);

        if ($email) {
            $builder->setShopperEmail($email);
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
        $email = $this->getCustomersEmailFromCart($cart);

        if ($email) {
            $builder->setShopperEmail($email);
        }
    }

    /**
     * @param Cart $cart
     *
     * @return string|null
     */
    private function getCustomersEmailFromCart(Cart $cart): ?string
    {
        $customer = new Customer($cart->id_customer);

        if (!$customer || !isset($customer->email)) {

            return null;
        }

        return $customer->email;
    }
}
