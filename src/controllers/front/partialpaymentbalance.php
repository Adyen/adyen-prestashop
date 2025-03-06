<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PartialPayment\Request\BalanceCheckRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Controllers\PaymentController;

class AdyenOfficialPartialPaymentBalanceModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPartialPaymentBalanceModuleFrontController';

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
    public function postProcess(): void
    {
        $data = Tools::getAllValues();
        $cart = $this->context->cart;
        $paymentMethod = $data['paymentMethod'] ?? null;
        $type = array_key_exists('brand', $paymentMethod) ? $paymentMethod['brand'] : '';
        $orderTotal = $cart->getOrderTotal($cart, $type);

        $response = CheckoutApi::get()->partialPayment((string)$cart->id_shop)
            ->checkBalance(new BalanceCheckRequest(
                $orderTotal,
                $currency->iso_code ?? 'EUR',
                $paymentMethod
            ));

        $result['response'] = $response->toArray();
        $result['majorValue'] = number_format(Amount::fromInt(
            $result['response']['balance']['value'],
            \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                $currency->iso_code ?? 'EUR'
            )
        )->getPriceInCurrencyUnits(), 2, '.', ',');
        $currency = Currency::getCurrencyInstance(Currency::getIdByIsoCode($currency->iso_code ?? 'EUR'));
        $result['currency'] = $currency->sign;
        $result['orderTotal'] = $orderTotal;

        AdyenPrestaShopUtility::dieJsonArray($result);
    }
}
