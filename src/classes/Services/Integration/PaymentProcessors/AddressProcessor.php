<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Address as PrestaAddress;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\BillingAddress;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\DeliveryAddress;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\AddressProcessor as PaymentLinkAddressProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\AddressProcessor as AddressProcessorInterface;
use Country as PrestaCountry;

/**
 * Class AddressProcessor
 */
class AddressProcessor implements AddressProcessorInterface, PaymentLinkAddressProcessorInterface
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
        $cart = new \Cart((int) $context->getReference());
        $country = $this->getCountry((string) $cart->id_address_invoice);
        $billingAddress = $this->getBillingAddress($cart);

        if ($billingAddress) {
            $builder->setBillingAddress($billingAddress);
            $builder->setCountryCode($country->iso_code);
        }

        $deliveryAddress = $this->getDeliveryAddress($cart);

        if ($deliveryAddress) {
            $builder->setDeliveryAddress($deliveryAddress);
        }
    }

    /**
     * @param PaymentLinkRequestBuilder $builder
     * @param PaymentLinkRequestContext $context
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        $cart = new \Cart((int) $context->getReference());
        $country = $this->getCountry((string) $cart->id_address_invoice);

        if ($billingAddress = $this->getBillingAddress($cart)) {
            $builder->setBillingAddress($billingAddress);
            $builder->setCountryCode($country->iso_code);
        }

        if ($deliveryAddress = $this->getDeliveryAddress($cart)) {
            $builder->setDeliveryAddress($deliveryAddress);
        }
    }

    /**
     * @param \Cart $cart
     *
     * @return BillingAddress|null
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getBillingAddress(\Cart $cart): ?BillingAddress
    {
        $country = $this->getCountry((string) $cart->id_address_invoice);
        $address = $this->formatAddressInfo($this->getAddress((string) $cart->id_address_invoice));

        if (!empty($address->address1)) {
            $stateIsoCode = $this->getStateIsoCode($country, $address);

            return new BillingAddress(
                $address->city ?? '',
                $country->iso_code ?: '',
                $address->address2 ?? '',
                $address->postcode ?? '',
                $stateIsoCode,
                $address->address1 ?? ''
            );
        }

        return null;
    }

    /**
     * @param \Cart $cart
     *
     * @return DeliveryAddress|null
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getDeliveryAddress(\Cart $cart): ?DeliveryAddress
    {
        $country = $this->getCountry((string) $cart->id_address_invoice);
        $address = $this->formatAddressInfo($this->getAddress((string) $cart->id_address_invoice));

        if ($cart->id_address_invoice === $cart->id_address_delivery && !empty($cart->id_address_delivery)) {
            $stateIsoCode = $this->getStateIsoCode($country, $address);

            return new DeliveryAddress(
                $address->city ?? '',
                $country->iso_code ?: '',
                $address->address2 ?? '',
                $address->postcode ?? '',
                $stateIsoCode,
                $address->address1 ?? ''
            );
        }

        if (!empty($cart->id_address_delivery)) {
            $deliveryCountry = $this->getCountry((string) $cart->id_address_delivery);
            $deliveryAddress = $this->formatAddressInfo($this->getAddress((string) $cart->id_address_delivery));
            $deliveryStateIsoCode = $this->getStateIsoCode($deliveryCountry, $deliveryAddress);

            return new DeliveryAddress(
                $deliveryAddress->city ?? '',
                $deliveryCountry->iso_code ?: '',
                $deliveryAddress->address2 ?? '',
                $deliveryAddress->postcode ?? '',
                $deliveryStateIsoCode,
                $deliveryAddress->address1 ?? ''
            );
        }

        return null;
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
     * @param string $id
     *
     * @return PrestaAddress
     */
    private function getAddress(string $id): PrestaAddress
    {
        return new PrestaAddress((int) $id);
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
        return new PrestaCountry(PrestaAddress::getCountryAndState((int) $id)['id_country']);
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
