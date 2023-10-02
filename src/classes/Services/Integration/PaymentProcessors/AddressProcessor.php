<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\BillingAddress;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\DeliveryAddress;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\AddressProcessor as AddressProcessorInterface;
use Country as PrestaCountry;
use Address as PrestaAddress;

/**
 * Class AddressProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
 */
class AddressProcessor implements AddressProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $cart = new \Cart($context->getReference());

        $country = $this->getCountry($cart->id_address_invoice);
        $address = $this->formatAddressInfo($this->getAddress($cart->id_address_invoice));

        if (!empty($address->address1)) {
            $stateIsoCode = $this->getStateIsoCode($country, $address);
            $this->setBillingAddress($address, $country->iso_code ?: '', $stateIsoCode, $builder);
            $builder->setCountryCode($country->iso_code);
        }

        if ($cart->id_address_invoice === $cart->id_address_delivery && !empty($cart->id_address_delivery)) {
            $stateIsoCode = $this->getStateIsoCode($country, $address);
            $this->setDeliveryAddress($address, $country->iso_code ?: '', $stateIsoCode, $builder);

            return;
        }

        if (!empty($cart->id_address_delivery)) {
            $deliveryCountry = $this->getCountry($cart->id_address_delivery);
            $deliveryAddress = $this->formatAddressInfo($this->getAddress($cart->id_address_delivery));
            $deliveryStateIsoCode = $this->getStateIsoCode($deliveryCountry, $deliveryAddress);

            $this->setDeliveryAddress(
                $deliveryAddress,
                $deliveryCountry->iso_code ?: '',
                $deliveryStateIsoCode,
                $builder
            );
        }
    }

    /**
     * @param PrestaAddress $address
     *
     * @return PrestaAddress
     */
    private function formatAddressInfo(PrestaAddress $address): PrestaAddress
    {
        $parts = preg_split('/\s+/', $address->address1);
        $streetName = implode(' ', array_slice($parts, 0, -1));
        $number = end($parts);

        if (is_numeric($number) && empty($address->address2)) {
            $address->address1 = $streetName;
            $address->address2 = $number;

            return $address;
        }

        return $address;
    }

    /**
     * @param PrestaAddress|null $address
     * @param string|null $country
     * @param string $stateIsoCode
     * @param PaymentRequestBuilder $builder
     *
     * @return void
     */
    private function setBillingAddress(
        ?PrestaAddress $address,
        ?string $country,
        string $stateIsoCode,
        PaymentRequestBuilder $builder
    ): void {
        $billingAddress = new BillingAddress(
            $address->city ?? '',
            $country ?? '',
            $address->address2 ?? '',
            $address->postcode ?? '',
            $stateIsoCode,
            $address->address1 ?? ''
        );

        $builder->setBillingAddress($billingAddress);
    }

    /**
     * @param PrestaAddress|null $address
     * @param string|null $country
     * @param string $stateIsoCode
     * @param PaymentRequestBuilder $builder
     *
     * @return void
     */
    private function setDeliveryAddress(
        ?PrestaAddress $address,
        ?string $country,
        string $stateIsoCode,
        PaymentRequestBuilder $builder
    ): void {
        $deliveryAddress = new DeliveryAddress(
            $address->city ?? '',
            $country ?? '',
            $address->address2 ?? '',
            $address->postcode ?? '',
            $stateIsoCode,
            $address->address1 ?? ''
        );

        $builder->setDeliveryAddress($deliveryAddress);
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
     * @param PrestaCountry $country
     * @param PrestaAddress $address
     *
     * @return string
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getStateIsoCode(PrestaCountry $country, PrestaAddress $address): string
    {
        return (new \State($address->id_state))->iso_code ?? '';
    }
}
