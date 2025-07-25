<?php

namespace AdyenPayment\Classes\Services;

use Address as PrestaAddress;
use Adyen\Core\BusinessLogic\AdminAPI\Response\TranslatableErrorResponse;
use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutConfig\Request\PaymentCheckoutConfigRequest;
use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutConfig\Response\PaymentCheckoutConfigResponse;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\MissingActiveApiConnectionData;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\MissingClientKeyConfiguration;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Country;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodCode;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodResponse;
use Adyen\Core\BusinessLogic\Domain\Payment\Exceptions\PaymentMethodDataEmptyException;
use Adyen\Core\BusinessLogic\Domain\Payment\Models\PaymentMethod;
use Cache;
use Carrier;
use Cart as PrestaCart;
use Configuration;
use Context;
use Country as PrestaCountry;
use Currency as PrestaCurrency;
use Customer as PrestaCustomer;
use Module;
use PrestaShopDatabaseException;
use PrestaShopException;
use Tools;

/**
 * Class CheckoutHandler
 *
 * @package AdyenPayment\Classes\Utility
 */
class CheckoutHandler
{
    /**
     * @param PrestaCart $cart
     *
     * @return PaymentCheckoutConfigResponse | TranslatableErrorResponse
     *
     * @throws InvalidCurrencyCode
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getPaymentCheckoutConfig(PrestaCart $cart, float $discountAmount)
    {
        $currency = new PrestaCurrency($cart->id_currency);
        $addressInvoice = new PrestaAddress($cart->id_address_invoice);
        $country = new PrestaCountry($addressInvoice->id_country);
        $customer = new PrestaCustomer($cart->id_customer);
        $shop = \Shop::getShop(\Context::getContext()->shop->id);

        return CheckoutAPI::get()->checkoutConfig($cart->id_shop)->getPaymentCheckoutConfig(
            new PaymentCheckoutConfigRequest(
                Amount::fromFloat(
                    $cart->getOrderTotal() - $discountAmount,
                    Currency::fromIsoCode(
                        $currency->iso_code
                    )
                ),
                $country->iso_code ? Country::fromIsoCode($country->iso_code) : null,
                Context::getContext()->getTranslator()->getLocale(),
                $shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . $cart->id_customer,
                $customer->email,
                $shop['name']
            )
        );
    }

    /**
     * @param PrestaCart $cart
     * @param bool $isGuest
     *
     * @return PaymentCheckoutConfigResponse
     *
     * @throws InvalidCurrencyCode
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws MissingActiveApiConnectionData
     * @throws MissingClientKeyConfiguration
     */
    public static function getExpressCheckoutConfig(PrestaCart $cart, bool $isGuest): PaymentCheckoutConfigResponse
    {
        $currency = new PrestaCurrency($cart->id_currency);
        $addressInvoice = new PrestaAddress($cart->id_address_invoice);
        $country = new PrestaCountry($addressInvoice->id_country);
        $shop = \Shop::getShop(\Context::getContext()->shop->id);
        $carrierId = '';

        if ($cart->id_carrier) {
            $carrierId = $cart->id_carrier;
        }

        $cart->id_carrier = CheckoutHandler::getCarrierId($cart);
        $cart->update();
        \Context::getContext()->cart->id_carrier = $cart->id_carrier;
        \Context::getContext()->cart->update();

        $expressCheckoutConfig = CheckoutAPI::get()->checkoutConfig($cart->id_shop)->getExpressPaymentCheckoutConfig(
            new PaymentCheckoutConfigRequest(
                Amount::fromFloat(
                    $cart->getOrderTotal(true, PrestaCart::BOTH, null, $cart->id_carrier),
                    Currency::fromIsoCode(
                        $currency->iso_code
                    )
                ),
                $country->iso_code ?
                    Country::fromIsoCode($country->iso_code) : Country::fromIsoCode(Context::getContext()->country->iso_code),
                Context::getContext()->getTranslator()->getLocale(),
                $shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . $cart->id_customer,
                null,
                null,
                $isGuest
            )
        );

        if ($carrierId) {
            $cart->id_carrier = $carrierId;
            $cart->update();
        }

        return $expressCheckoutConfig;
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    public static function getCarrierId(PrestaCart $cart): int
    {
        if (Tools::getValue('controller') === 'paymentconfigexpresscheckout' ||
            Tools::getValue('controller') === 'paymentproduct' ||
            (Tools::getValue('controller') === 'payment' && !$cart->id_carrier)
        ) {
            if ($cart->id_carrier) {
                $carrier = new Carrier($cart->id_carrier);

                if (self::isCarrierAvailable($cart, $carrier) && $carrier->active) {
                    return $cart->id_carrier;
                }
            }

            //Get the default carrier for current shop
            $carrierId = (int)Configuration::get('PS_CARRIER_DEFAULT', null, null, $cart->id_shop);
            $carrier = new Carrier($carrierId);

            if (self::isCarrierAvailable($cart, $carrier) && $carrier->active) {
                return $carrierId;
            }

            $address = new PrestaAddress($cart->id_address_delivery);
            $country = new PrestaCountry($address->id_country);
            $carriers = Carrier::getCarriers(
                Context::getContext()->language->id,
                true,
                false,
                $country->id_zone,
                null,
                Carrier::ALL_CARRIERS
            );

            foreach ($carriers as $carrier) {
                $carrier = new Carrier((int)$carrier['id_carrier']);

                if (self::isCarrierAvailable($cart, $carrier)) {
                    return $carrier->id;
                }
            }

            return 0;
        }

        return (int)$cart->id_carrier;
    }

    /**
     * @param PaymentCheckoutConfigResponse $config
     *
     * @return PaymentMethod[]
     *
     * @throws PaymentMethodDataEmptyException
     */
    public static function getAvailablePaymentMethods(PaymentCheckoutConfigResponse $config): array
    {
        $paymentMethodsConfiguration = $config->getPaymentMethodsConfiguration();
        $paymentMethodResponse = $config->getPaymentMethodResponse();

        $mappedPaymentMethods = array_map(function ($paymentMethod) use ($paymentMethodResponse) {
            foreach ($paymentMethodResponse as $response) {
                if ($paymentMethod->getCode() === $response->getType()) {
                    return $paymentMethod;
                }
            }
        }, $paymentMethodsConfiguration);

        $availableMethods = array_merge(
            $mappedPaymentMethods,
            static::getOneyMethods($paymentMethodsConfiguration, $paymentMethodResponse)
        );

        return array_filter($availableMethods);
    }

    /**
     * @param PaymentMethod[] $paymentMethodsConfiguration
     * @param PaymentMethodResponse[] $paymentMethodResponse
     *
     * @return array
     *
     * @throws PaymentMethodDataEmptyException
     */
    private static function getOneyMethods(array $paymentMethodsConfiguration, array $paymentMethodResponse): array
    {
        $oneyMethods = [];

        foreach ($paymentMethodsConfiguration as $paymentMethod) {
            foreach ($paymentMethodResponse as $response) {
                if (PaymentMethodCode::isOneyMethod($response->getType())
                    && PaymentMethodCode::isOneyMethod($paymentMethod->getCode())) {
                    foreach ($paymentMethod->getAdditionalData()->getSupportedInstallments() as $installment) {
                        if (strpos($response->getType(), $installment)) {
                            $oneyMethods[] = new PaymentMethod(
                                $paymentMethod->getMethodId(),
                                'facilypay_' . $installment . 'x',
                                $paymentMethod->getName() . ' ' . $installment . 'x',
                                $paymentMethod->getLogo(),
                                $paymentMethod->isStatus(),
                                $paymentMethod->getCurrencies(),
                                $paymentMethod->getCountries(),
                                $paymentMethod->getPaymentType(),
                                $paymentMethod->getDescription(),
                                $paymentMethod->getSurchargeType(),
                                $paymentMethod->getFixedSurcharge(),
                                $paymentMethod->getPercentSurcharge(),
                                $paymentMethod->getSurchargeLimit(),
                                $paymentMethod->getDocumentationUrl()
                            );
                            continue 2;
                        }
                    }
                }
            }
        }

        return $oneyMethods;
    }

    /**
     * Check if carrier is available for Adyen module.
     *
     * @param PrestaCart $cart
     * @param Carrier $carrier
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private static function isCarrierAvailable(PrestaCart $cart, Carrier $carrier): bool
    {
        $address = new PrestaAddress($cart->id_address_delivery);
        $country = new PrestaCountry($address->id_country);
        $availableCarriers = Carrier::getCarriersForOrder($country->id_zone, [], $cart);
        $isAvailable = false;

        foreach ($availableCarriers as $availableCarrier) {
            if ((int)$availableCarrier['id_carrier'] === (int)$carrier->id) {
                $isAvailable = true;
            }
        }

        $sql = 'SELECT c.*
				FROM `' . _DB_PREFIX_ . 'module_carrier` mc
				LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c ON c.`id_reference` = mc.`id_reference`
				WHERE mc.`id_module` = ' . (int)Module::getModuleIdByName('adyenofficial') . '
					AND c.`active` = 1
					AND mc.id_shop = ' . (int)$cart->id_shop . '
					AND mc.id_reference = ' . $carrier->id_reference . '
				ORDER BY c.`name` ASC';

        return $isAvailable && !empty(\Db::getInstance()->executeS($sql));
    }
}
