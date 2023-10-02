<?php

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodsRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Proxies\PaymentsProxy;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\Domain\CreditCardsService;
use AdyenPayment\Classes\Utility\Url;

/**
 * Class AdyenOfficialCreditCardsModuleFrontController
 */
class AdyenOfficialCreditCardsModuleFrontController extends ModuleFrontController
{
    private $connectionSettingsRepository;

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->connectionSettingsRepository = ServiceRegister::getService(
            Adyen\Core\BusinessLogic\Domain\Connection\Repositories\ConnectionSettingsRepository::class
        );
    }

    /**
     * @throws PrestaShopException
     */
    public function initContent(): void
    {
        parent::initContent();
        Tools::clearAllCache();
        $this->setTemplate('module:adyenofficial/views/templates/front/credit-cards.tpl');
    }

    /**
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Exceptions\InvalidCurrencyCode
     * @throws Exception
     */
    public function postProcess(): void
    {
        StoreContext::doWithStore((string)\Context::getContext()->shop->id, function () {
            $this->renderStoredCards();
        });
    }

    /**
     * @return array
     */
    public function getBreadcrumbLinks(): array
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();

        $breadcrumb['links'][] = [
            'title' => $this->trans('Stored credit cards', [], 'Shop.Theme.Global')
        ];

        return $breadcrumb;
    }

    /**
     * @return void
     */
    private function renderStoredCards(): void
    {
        $proxy = ServiceRegister::getService(PaymentsProxy::class);
        $creditCardsService = ServiceRegister::getService(CreditCardsService::class);
        $connectionSettings = $this->connectionSettingsRepository->getConnectionSettings()->getActiveConnectionData();
        $shop = \Shop::getShop(\Context::getContext()->shop->id);


        $paymentMethods = $proxy->getAvailablePaymentMethods(
            new PaymentMethodsRequest(
                $connectionSettings->getMerchantId(),
                ['scheme'],
                null,
                null,
                null,
                ShopperReference::parse(
                    $shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . \Context::getContext(
                    )->customer->id
                )
            )

        );

        $storedPaymentMethodsInfo = [];

        if ($creditCardsService->creditCardEnabled()) {
            foreach ($paymentMethods->getStoredPaymentMethodsResponse() as $method) {
                $storedPayment = [
                    'id' => $method->getMetadata()['id'],
                    'lastFour' => $method->getMetadata()['lastFour'],
                    'name' => $method->getMetadata()['name'],
                    'expiryDate' => $method->getMetadata()['expiryMonth'] . '/' . $method->getMetadata()['expiryYear'],

                ];

                $storedPaymentMethodsInfo[] = $storedPayment;
            }
        }

        $this->context->smarty->assign(
            [
                'storedPaymentMethods' => $storedPaymentMethodsInfo,
                'numberOfStoredPaymentMethods' => count($storedPaymentMethodsInfo),
                'deletionUrl' => Url::getFrontUrl(
                    'carddelete',
                    [
                        'customerId' => \Context::getContext()->customer->id
                    ]
                )
            ]
        );
    }
}
