<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\BirthdayProcessor as BirthdayProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperBirthdayProcessor as PaymentLinkShopperBirthdayProcessorInterface;
use Cart;
use Customer;


/**
 * Class BirthdayProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class BirthdayProcessor implements BirthdayProcessorInterface, PaymentLinkShopperBirthdayProcessorInterface
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
        $customersBirthday = $this->getCustomersBirthdayFromCart($cart);

        if ($customersBirthday) {
            $builder->setDateOfBirth($customersBirthday);
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
        $customersBirthday = $this->getCustomersBirthdayFromCart($cart);

        if ($customersBirthday) {
            $builder->setDateOfBirth($customersBirthday);
        }
    }

    /**
     * @param Cart $cart
     *
     * @return string|null
     */
    private function getCustomersBirthdayFromCart(Cart $cart): ?string
    {
        $customer = new Customer($cart->id_customer);

        if (!$customer || strtotime($customer->birthday) < 0 || !strtotime($customer->birthday)) {
            return null;
        }

        return $customer->birthday;
    }
}
