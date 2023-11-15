<?php

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\PaymentMethodsRequest;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\ShopperReference;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Proxies\PaymentsProxy;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Proxies\StoredDetailsProxy;
use Adyen\Core\BusinessLogic\Domain\Connection\Repositories\ConnectionSettingsRepository;
use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Payment\Services\PaymentService;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\CheckoutHandler;
use AdyenPayment\Classes\Services\Domain\CreditCardsService;
use AdyenPayment\Classes\Utility\Url;

/**
 * Class AdyenOfficialCreditCardsModuleFrontController
 */
class AdyenOfficialStoredMethodsModuleFrontController extends ModuleFrontController
{
    /**
     * @var ConnectionSettingsRepository
     */
    private $connectionSettingsRepository;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var PaymentsProxy
     */
    private $paymentsProxy;

    /**
     * @var StoredDetailsProxy
     */
    private $storedDetailsProxy;

    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->connectionSettingsRepository = ServiceRegister::getService(ConnectionSettingsRepository::class);
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
            'title' => $this->trans('Stored payment methods', [], 'Shop.Theme.Global')
        ];

        return $breadcrumb;
    }

    /**
     * @return void
     * @throws Exception
     */
    private function renderStoredCards(): void
    {
        $this->paymentService = ServiceRegister::getService(PaymentService::class);
        $this->paymentsProxy = ServiceRegister::getService(PaymentsProxy::class);
        $this->storedDetailsProxy = ServiceRegister::getService(StoredDetailsProxy::class);
        $connectionSettings = $this->connectionSettingsRepository->getConnectionSettings()->getActiveConnectionData();
        $shop = Shop::getShop(Context::getContext()->shop->id);
        $shopperReference = ShopperReference::parse(
            $shop['domain'] . '_' . Context::getContext()->shop->id . '_' . Context::getContext()->customer->id
        );

        $paymentMethods = $this->paymentsProxy->getAvailablePaymentMethods(
            new PaymentMethodsRequest(
                $connectionSettings->getMerchantId(),
                ['scheme'],
                null,
                null,
                null,
                $shopperReference
            )
        );

        $storedPayments = $this->storedDetailsProxy->getStoredPaymentDetails(
            $shopperReference,
            $connectionSettings->getMerchantId()
        );

        $storedPaymentMethodsInfo = [];

        if ($configuredPaymentMethod = $this->paymentService->getPaymentMethodByCode(
            PaymentService::CREDIT_CARD_CODE
        )) {
            foreach ($paymentMethods->getStoredPaymentMethodsResponse() as $method) {
                $storedPayment = [
                    'id' => $method->getMetadata()['id'],
                    'lastFour' => $method->getMetadata()['lastFour'],
                    'name' => $method->getMetadata()['name'],
                    'expiryDate' => $method->getMetadata()['expiryMonth'] . '/' . $method->getMetadata()['expiryYear'],
                    'isCreditCard' => true,
                    'logo' => $configuredPaymentMethod->getLogo()
                ];

                $storedPaymentMethodsInfo[] = $storedPayment;
            }
        }

        foreach ($storedPayments as $method) {
            $methodCode = $method->getMetadata()['RecurringDetail']['variant'];
            $configuredPaymentMethod = $this->paymentService->getPaymentMethodByCode($methodCode);
            if (!$configuredPaymentMethod || !$configuredPaymentMethod->getEnableTokenization()) {
                continue;
            }

            $storedPayment = [
                'id' => $method->getMetadata()['RecurringDetail']['recurringDetailReference'],
                'name' => $configuredPaymentMethod->getName(),
                'createdAt' => (new DateTime($method->getMetaData()['RecurringDetail']['creationDate']))->format(
                    'Y-m-d'
                ),
                'isCreditCard' => false,
                'logo' => $configuredPaymentMethod->getLogo()
            ];

            $storedPaymentMethodsInfo[] = $storedPayment;
        }

        $this->context->smarty->assign(
            [
                'storedPaymentMethods' => $storedPaymentMethodsInfo,
                'numberOfStoredPaymentMethods' => count($storedPaymentMethodsInfo),
                'deletionUrl' => Url::getFrontUrl(
                    'storedmethoddelete',
                    [
                        'customerId' => Context::getContext()->customer->id
                    ]
                )
            ]
        );
    }
}
