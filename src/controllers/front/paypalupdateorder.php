<?php


use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\Integration\CustomerService;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Controllers\PaymentController;
use Currency as PrestaCurrency;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency;

/**
 * Class AdyenOfficialPayPalUpdateOrderModuleFrontController
 */
class AdyenOfficialPayPalUpdateOrderModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPayPalUpdateOrderModuleFrontController';

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function postProcess()
    {
        $requestData = Tools::getAllValues();

        Logger::logDebug(
            'Received paypalupdateorder request',
            'Integration',
            ['request' => json_encode($requestData)]
        );

        $amount = $this->getConfigForNewAddress($requestData);

        $response = CheckoutAPI::get()
            ->paymentRequest((string)Context::getContext()->shop->id)->paypalUpdateOrder(
                [
                    'amount' => $amount,
                    'paymentData' => $requestData['paymentData'],
                    'pspReference' => $requestData['pspReference'],
                ]
            );

        if ($response->getStatus() === 'success') {
            AdyenPrestaShopUtility::dieJsonArray(['paymentData' => $response->getPaymentData()]);
        }
    }

    private function getConfigForNewAddress($data)
    {
        $shippingAddress = $data['shippingAddress'];
        $countryCode = $shippingAddress['countryCode'];
        /** @var CustomerService $customerService */
        $customerService = ServiceRegister::getService(CustomerService::class);
        if (!$customerService->verifyIfCountryNotRestricted($countryCode, (int)$this->context->language->id)) {
            AdyenPrestaShopUtility::die400(["message" => "Invalid country code"]);
        }

        $cart = $this->addProductsToCart();

        $countryId = Country::getByIso($countryCode);
        $address = new Address();
        $address->country = $countryCode;
        $address->city = $shippingAddress['city'];
        $address->address1 = 'Street';
        $address->postcode = $shippingAddress['postalCode'];
        $address->alias = 'Home';
        $address->lastname = 'Doe';
        $address->firstname = 'John';
        $address->phone = '1234567890';
        $address->id_customer = 0;
        $address->id_country = $countryId;
        $address->save();
        $cart->id_address_delivery = $address->id;
        $cart->update();

        $total = $cart->getOrderTotal();
        $currency = new PrestaCurrency($cart->id_currency);
        $cart->delete();
        $address->delete();

        return Amount::fromFloat($total, Currency::fromIsoCode($currency->iso_code));
    }

    private function addProductsToCart(): Cart
    {
        $currencyId = (int)$this->context->currency->id;
        $langId = (int)$this->context->language->id;
        $cart = $this->createEmptyCart($currencyId, $langId);
        $productId = (int)Tools::getValue('id_product');
        $productAttributeId = (int)Tools::getValue('id_product_attribute');
        $quantityWanted = (int)Tools::getValue('quantity_wanted');

        if ($quantityWanted === 0) {
            $quantityWanted = 1;
        }

        $customizationId = (int)Tools::getValue('id_customization');
        $cart->updateQty($quantityWanted, $productId, $productAttributeId, $customizationId);

        return $cart;
    }

    /**
     * @param int $currencyId
     * @param int $langId
     * @return Cart
     * @throws PrestaShopException
     */
    private function createEmptyCart(int $currencyId, int $langId): Cart
    {
        $cart = new Cart();
        $cart->id_currency = $currencyId;
        $cart->id_lang = $langId;
        $cart->save();

        return $cart;
    }
}
