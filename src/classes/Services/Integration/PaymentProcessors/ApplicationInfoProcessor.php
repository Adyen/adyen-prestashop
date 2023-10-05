<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ApplicationInfo\ApplicationInfo;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ApplicationInfo\ExternalPlatform;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\ApplicationInfoProcessor as ApplicationInfoProcessorInterface;
use Configuration;
use Module;

/**
 * Class ApplicationInfoProcessor
 *
 * @package AdyenPayment\Classes\Services\Integration\PaymentProcessors
 */
class ApplicationInfoProcessor implements ApplicationInfoProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $shopName = Configuration::get('PS_SHOP_NAME');
        $moduleInstance = Module::getInstanceByName('adyenofficial');

        $shopName && $builder->setApplicationInfo(
            new ApplicationInfo(new ExternalPlatform($shopName, _PS_VERSION_), $moduleInstance->version ?? null)
        );
    }
}
