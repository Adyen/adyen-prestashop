<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ApplicationInfo\ApplicationInfo;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ApplicationInfo\ExternalPlatform;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\ApplicationInfoProcessor as ApplicationInfoProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\ApplicationInfoProcessor as PaymentLinkApplicationInfoProcessorInterface;
use Configuration;
use Module;

/**
 * Class ApplicationInfoProcessor
 *
 * @package AdyenPayment\Classes\Services\Integration\PaymentProcessors
 */
class ApplicationInfoProcessor implements ApplicationInfoProcessorInterface,
                                          PaymentLinkApplicationInfoProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $builder->setApplicationInfo($this->getApplicationInfo());
    }

    /**
     * @param PaymentLinkRequestBuilder $builder
     * @param PaymentLinkRequestContext $context
     *
     * @return void
     */
    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        $builder->setApplicationInfo($this->getApplicationInfo());
    }

    /**
     * @return ApplicationInfo
     */
    private function getApplicationInfo(): ApplicationInfo
    {
        $shopName = Configuration::get('PS_SHOP_NAME');
        $moduleInstance = Module::getInstanceByName('adyenofficial');

        return new ApplicationInfo(new ExternalPlatform($shopName, _PS_VERSION_), $moduleInstance->version ?? null);
    }
}
