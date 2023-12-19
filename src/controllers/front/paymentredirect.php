<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\Infrastructure\Logger\Logger;
use AdyenPayment\Classes\Bootstrap;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Controllers\PaymentController;

/**
 * Class AdyenOfficialPaymentRedirectModuleFrontController
 */
class AdyenOfficialPaymentRedirectModuleFrontController extends PaymentController
{
    /** @var string File name for translation contextualization */
    const FILE_NAME = 'AdyenOfficialPaymentRedirectModuleFrontController';

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
            'Received handleRedirectAction request',
            'Integration',
            ['request' => json_encode($requestData)]
        );

        $cart = $this->getCurrentCart();

        if (!\Validate::isLoadedObject($cart)) {
            Tools::redirect($this->context->link->getPageLink('order', $this->ssl));
        }

        $response = CheckoutAPI::get()
            ->paymentRequest((string)$cart->id_shop)
            ->updatePaymentDetails(array_key_exists('details', $requestData) ? $requestData : ['details' => $requestData]);

        if (!$response->isSuccessful()) {
            $message = $this->module->l('Your payment could not be processed, please resubmit order.', self::FILE_NAME);
            $this->errors[] = $message;
            $this->redirectWithNotifications(
                Context::getContext()->link->getPageLink('order')
            );
        }

        try {
            $this->saveOrder(Tools::getValue('adyenPaymentType'), $cart);

            if (isset($requestData['details'])) {
                die(json_encode(['nextStepUrl' => $this->generateSuccessURL($cart)]));
            }

            Tools::redirect($this->generateSuccessURL($cart));
        } catch (Throwable $e) {
            Logger::logError(
                'Adyen failed to create order from Cart with ID: ' . $cart->id . ' Reason: ' . $e->getMessage()
            );
            $message = $this->module->l('Your payment could not be processed, please resubmit order.', self::FILE_NAME);
            $this->errors[] = $message;
            $this->redirectWithNotifications(Context::getContext()->link->getPageLink('order'));
        }
    }

    /**
     * @return Cart
     */
    protected function getCurrentCart(): Cart
    {
        return new Cart(Tools::getValue('adyenMerchantReference'));
    }
}
