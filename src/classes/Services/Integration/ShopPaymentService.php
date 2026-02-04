<?php

namespace AdyenPayment\Classes\Services\Integration;

use Adyen\Core\BusinessLogic\Domain\Integration\Payment\ShopPaymentService as ShopPaymentServiceInterface;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\PaymentMethod;
use Adyen\Core\BusinessLogic\Domain\Payment\Repositories\PaymentMethodConfigRepository;
use AdyenPayment\Classes\Services\ImageHandler;

/**
 * Class ShopPaymentService
 */
class ShopPaymentService implements ShopPaymentServiceInterface
{
    /**
     * @var PaymentMethodConfigRepository
     */
    private $paymentMethodRepository;

    /**
     * @param PaymentMethodConfigRepository $paymentMethodRepository
     */
    public function __construct(PaymentMethodConfigRepository $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function createPaymentMethod(PaymentMethod $method): void
    {
    }

    /**
     * @param PaymentMethod $method
     *
     * @return void
     */
    public function updatePaymentMethod(PaymentMethod $method): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function deletePaymentMethod(string $methodId): void
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function deleteAllPaymentMethods(): void
    {
        $storeId = (string) \Context::getContext()->shop->id;

        foreach ($this->paymentMethodRepository->getConfiguredPaymentMethods() as $paymentMethod) {
            ImageHandler::removeImage($paymentMethod->getMethodId(), $storeId);
        }
    }
}
