<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ShopperBirthdayProcessor as PaymentLinkShopperBirthdayProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\BirthdayProcessor as BirthdayProcessorInterface;

/**
 * Class BirthdayProcessor
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
        if ($customersBirthday = $this->getCustomersBirthdayFromCartId((int) $context->getReference())) {
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
        if ($customersBirthday = $this->getCustomersBirthdayFromCartId((int) $context->getReference())) {
            $builder->setDateOfBirth($customersBirthday);
        }
    }

    /**
     * @param int $cartId
     *
     * @return string|null
     */
    private function getCustomersBirthdayFromCartId(int $cartId): ?string
    {
        $cart = new \Cart($cartId);
        $customer = new \Customer($cart->id_customer);

        if (strtotime($customer->birthday) < 0 || !strtotime($customer->birthday)) {
            return null;
        }

        return $customer->birthday;
    }
}
