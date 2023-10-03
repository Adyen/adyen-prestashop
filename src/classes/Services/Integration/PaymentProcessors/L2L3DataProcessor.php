<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\AdditionalData\EnhancedSchemeData;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\AdditionalData\ItemDetailLine;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\L2L3DataProcessor as L2L3DataProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Payment\Services\PaymentService;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\AdditionalData\AdditionalData;
use Country as PrestaCountry;
use Address as PrestaAddress;

/**
 * Class L2L3DataProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class L2L3DataProcessor implements L2L3DataProcessorInterface
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     *
     * @throws \Exception
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $cart = new \Cart($context->getReference());
        $country = $this->getCountry($cart->id_address_delivery);
        $address = $this->getAddress($cart->id_address_delivery);

        if (!$this->shouldSyncL2L3Data((string)$context->getPaymentMethodCode())) {
            return;
        }

        $additionalData = new AdditionalData(
            null,
            new EnhancedSchemeData(
                $cart->getOrderTotal() ? (string)($cart->getOrderTotal() - $cart->getOrderTotal(false)) : '',
                $cart->id_customer ? (string)$cart->id_customer : '',
                $this->getTotalShippingCost($cart),
                '',
                (new \DateTime())->format('dMy'),
                '',
                $address->id_state ? (new \State($address->id_state))->iso_code : '',
                $country->iso_code ?? '',
                $address->postcode ?? '',
                $this->getDetails($cart->getProducts())
            )
        );

        $builder->setAdditionalData($additionalData);
    }

    /**
     * @param string $code
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function shouldSyncL2L3Data(string $code): bool
    {
        $creditCardConfig = $this->paymentService->getPaymentMethodByCode($code);

        if ($creditCardConfig) {
            return $creditCardConfig->getAdditionalData()->isSendBasket();
        }

        return false;
    }

    /**
     * @param array $basketContent
     *
     * @return array
     */
    private function getDetails(array $basketContent): array
    {
        $details = [];

        foreach ($basketContent as $item) {
            $details[] = new ItemDetailLine(
                strip_tags($item['description_short']) ?? '',
                $item['upc'] ?? '',
                $item['quantity'] ?? 0,
                '',
                $item['unit_price'] ?? 0,
                '',
                '',
                ''
            );
        }

        return $details;
    }

    /**
     * @param \Cart $cart
     *
     * @return string
     */
    private function getTotalShippingCost(\Cart $cart): string
    {
        return ($cart->getTotalShippingCost() >= 0.0) ? (string)$cart->getTotalShippingCost() : '';
    }

    /**
     * @param string $id
     *
     * @return PrestaCountry
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getCountry(string $id): PrestaCountry
    {
        return new PrestaCountry(PrestaAddress::getCountryAndState($id)['id_country']);
    }

    /**
     * @param string $id
     *
     * @return PrestaAddress
     */
    private function getAddress(string $id): PrestaAddress
    {
        return new \Address($id);
    }
}
