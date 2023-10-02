<?php

class AdminOrdersController extends AdminOrdersControllerCore
{
    private const PRESTA_ORDER_DRAFT_PAYMENT = 'payment';
    private const ADYEN_ORDER_DRAFT_PSP_REFERENCE = 'adyen_order_draft_psp_reference';
    private const ADYEN_ORDER_DRAFT_PAYMENT_METHOD = 'adyen_order_draft_payment_method';

    /**
     * @var \AdyenPayment\Classes\Overrides\AdminOrdersController
     */
    private $adminOrderController;

    public function __construct()
    {
        parent::__construct();

        $this->initializeAdyenHandler();
    }

    /**
     * @param string $orderId
     * @return string
     *
     * @throws SmartyException
     */
    public function getOrderPspReference(string $orderId): string
    {
        return $this->adminOrderController->getOrderPspReference($orderId, $this->context);
    }

    /**
     * @param string $orderId
     * @return string
     *
     * @throws SmartyException
     */
    public function getOrderPaymentMethod(string $orderId): string
    {
        return $this->adminOrderController->getOrderPaymentMethod($orderId, $this->context);
    }

    /**
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function initializeAdyenHandler(): void
    {
        require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

        $this->adminOrderController = new \AdyenPayment\Classes\Overrides\AdminOrdersController();

        $this->fields_list = $this->adminOrderController->insertAdyenPaymentColumn(
            $this->fields_list,
            $this->l('Adyen Psp Reference'),
            'getOrderPspReference',
            self::PRESTA_ORDER_DRAFT_PAYMENT,
            self::ADYEN_ORDER_DRAFT_PSP_REFERENCE
        );

        $this->fields_list = $this->adminOrderController->insertAdyenPaymentColumn(
            $this->fields_list,
            $this->l('Adyen Payment Method'),
            'getOrderPaymentMethod',
            self::ADYEN_ORDER_DRAFT_PSP_REFERENCE,
            self::ADYEN_ORDER_DRAFT_PAYMENT_METHOD
        );
    }
}
