<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\BirthdayProcessor as BirthdayProcessorInterface;

/**
 * Class BirthdayProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class BirthdayProcessor implements BirthdayProcessorInterface
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

        if (!$customer || strtotime($customer->birthday) < 0 || !strtotime($customer->birthday)) {
            return;
        }

        $builder->setDateOfBirth($customer->birthday);
    }
}
