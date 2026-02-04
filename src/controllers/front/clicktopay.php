<?php

use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\SessionService;
use AdyenPayment\Classes\Utility\Url;
use AdyenPayment\Controllers\PaymentController;

class AdyenOfficialClickToPayModuleFrontController extends PaymentController
{
    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();

        if ($this->isHummigbirdTheme()) {
            $this->setTemplate('module:adyenofficial/views/templates/front/adyen-additional-details-hummingbird.tpl');

            return;
        }

        $this->setTemplate('module:adyenofficial/views/templates/front/adyen-additional-details.tpl');
    }

    public function postProcess()
    {
        $cartId = SessionService::get('cartId');

        $this->context->smarty->assign(
            [
                'action' => SessionService::get('adyenAction'),
                'paymentRedirectActionURL' => Url::getFrontUrl(
                    'paymentredirect',
                    [
                        'adyenMerchantReference' => $cartId,
                        'adyenPaymentType' => SessionService::get('adyenPaymentMethodType'),
                    ]
                ),
                'checkoutConfigUrl' => Url::getFrontUrl('paymentconfig'),
                'checkoutUrl' => $this->context->link->getPageLink('order', $this->ssl, null),
            ]
        );
    }
}
