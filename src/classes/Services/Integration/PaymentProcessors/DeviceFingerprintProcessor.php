<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\DeviceFingerprintProcessor as DeviceFingerprintProcessorInterface;

/**
 * Class DeviceFingerprintProcessor
 */
class DeviceFingerprintProcessor implements DeviceFingerprintProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
    }
}
