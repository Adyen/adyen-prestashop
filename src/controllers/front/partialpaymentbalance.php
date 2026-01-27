<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\PartialPayment\Request\BalanceCheckRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Amount;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;
use AdyenPayment\Classes\Version\Contract\VersionHandler;
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
        $remainingAmount = (float)Tools::getValue('remainingAmount');
        $type = array_key_exists('brand', $paymentMethod) ? $paymentMethod['brand'] : '';
        $orderTotal = $this->getOrderTotal($cart, $type);
        $remainingValue = Amount::fromFloat(
            $remainingAmount !== -1.00 ? $remainingAmount : $orderTotal,
            \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                $currency->iso_code ?? 'EUR'
            ));

        $response = CheckoutApi::get()->partialPayment((string)$cart->id_shop)
            ->checkBalance(new BalanceCheckRequest(
                $remainingValue->getPriceInCurrencyUnits(),
                $currency->iso_code ?? 'EUR',
                $paymentMethod
            ));

        /** @var VersionHandler $versionHandler */
        $versionHandler = ServiceRegister::getService(VersionHandler::class);
        $precision = $versionHandler->getPrecision();

        $responseArray = $response->toArray();
        $result['response'] = $responseArray;

        if (!$response->isSuccessful()) {
            $result['success'] = false;
            AdyenPrestaShopUtility::dieJsonArray($result);

            return;
        }

        $result['success'] = true;
        $result['majorValue'] = number_format(Amount::fromInt(
            $remainingValue->getValue() > $responseArray['balance']['value'] ? $responseArray['balance']['value'] : $remainingValue->getValue(),
            \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                $currency->iso_code ?? 'EUR'
            )
        )->getPriceInCurrencyUnits(), $precision, '.', ',');
        $result['minorValue'] = $remainingValue->getValue() > $responseArray['balance']['value'] ? $responseArray['balance']['value'] : $remainingValue->getValue();
        $result['responseCurrency'] = $responseArray['balance']['currency'];
        $result['resultCode'] = $responseArray['resultCode'];
        $currency = Currency::getCurrencyInstance(Currency::getIdByIsoCode($currency->iso_code ?? 'EUR'));
        $result['currency'] = $currency->sign;
        $result['orderTotal'] = $orderTotal;
        $result['minorOrderTotal'] = Amount::fromFloat(
            $orderTotal,
            \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\Amount\Currency::fromIsoCode(
                $currency->iso_code ?? 'EUR'
            )
        )->getValue();

        AdyenPrestaShopUtility::dieJsonArray($result);
    }
}
