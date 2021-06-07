<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\controllers;

use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\Logger;
use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\AdyenException;
use Adyen\PrestaShop\service\Cart as CartService;
use Adyen\PrestaShop\model\AdyenPaymentResponse;
use Adyen\PrestaShop\service\Order as OrderService;
use Adyen\PrestaShop\service\OrderPaymentService;

abstract class FrontController extends \ModuleFrontController
{
    /**
     * List of approved root keys from the state.data in the frontend checkout components
     * Available in the php api library from version 7.0.0
     *
     * @var string[]
     */
    protected $stateDataRootKeys = array(
        'paymentMethod',
        'billingAddress',
        'deliveryAddress',
        'riskData',
        'shopperName',
        'dateOfBirth',
        'telephoneNumber',
        'shopperEmail',
        'countryCode',
        'socialSecurityNumber',
        'browserInfo',
        'installments',
        'storePaymentMethod',
        'conversionId',
        'paymentData',
        'details'
    );

    const BROWSER_INFO = 'browserInfo';
    const USER_AGENT = 'userAgent';
    const ACCEPT_HEADER = 'acceptHeader';

    const ADYEN_MERCHANT_REFERENCE = 'adyenMerchantReference';
    const ISSUER = 'issuer';
    const PA_REQUEST = 'paRequest';
    const MD = 'md';
    const ISSUER_URL = 'issuerUrl';
    const REDIRECT_METHOD = 'redirectMethod';

    /**
     * @var AdyenHelper
     */
    protected $helperData;

    /**
     * @var VersionChecker
     */
    protected $versionChecker;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var AdyenPaymentResponse
     */
    protected $adyenPaymentResponseModel;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var OrderAdapter
     */
    private $orderAdapter;

    /**
     * @var Adyen\Util\Currency
     */
    private $utilCurrency;

    /**
     * @var OrderPaymentService
     */
    private $orderPaymentService;

    /**
     * FrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->versionChecker = ServiceLocator::get('Adyen\PrestaShop\application\VersionChecker');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        $this->cartService = ServiceLocator::get('Adyen\PrestaShop\service\Cart');
        $this->adyenPaymentResponseModel = ServiceLocator::get('Adyen\PrestaShop\model\AdyenPaymentResponse');
        $this->orderService = ServiceLocator::get('Adyen\PrestaShop\service\Order');
        $this->orderAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter');
        $this->utilCurrency = ServiceLocator::get('Adyen\Util\Currency');
        $this->orderPaymentService = ServiceLocator::get('Adyen\PrestaShop\service\OrderPaymentService');
    }

    /**
     * This controller handles ajax and non ajax form submissions as well, both server side and client side redirects
     * needs to be handled based on the $isAjax parameter
     *
     * @param string $pageLink
     * @param bool $isAjax
     * @throws AdyenException
     */
    protected function redirectUserToPageLink($pageLink, $isAjax = false)
    {
        if (!$isAjax) {
            \Tools::redirect($pageLink);
        } else {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'redirect',
                    array(
                        'redirectUrl' => $pageLink
                    )
                )
            );
        }
    }

    /**
     * @param null $value
     * @param null $controller
     * @param null $method
     * @throws PrestaShopException
     */
    protected function ajaxRender($value = null, $controller = null, $method = null)
    {
        header('content-type: application/json; charset=utf-8');
        if (method_exists('\ControllerCore', 'ajaxRender')) {
            parent::ajaxRender($value, $controller, $method);
            exit;
        } else {
            $this->ajax = true;
            parent::ajaxDie($value, $controller, $method);
        }
    }

    /**
     * @return \Cart
     */
    protected function getCurrentCart()
    {
        return new \Cart($this->context->cart->id);
    }

    /**
     * @param $response
     * @param \Cart $cart
     * @param $customer
     * @param $isAjax
     * @param array $paymentRequest
     * @throws AdyenException
     */
    protected function handleAdyenApiResponse($response, \Cart $cart, $customer, $isAjax, $paymentRequest = array())
    {
        $resultCode = $response['resultCode'];

        $extraVars = array();
        if (!empty($response['pspReference'])) {
            $extraVars['transaction_id'] = $response['pspReference'];
        }

        $paymentRequestAmount = null;
        $paymentRequestCurrency = null;
        if (!empty($paymentRequest['amount'])) {
            $paymentRequestAmount = $paymentRequest['amount']['value'];
            $paymentRequestCurrency = $paymentRequest['amount']['currency'];
        }

        $orderNeedsAttention = false;
        // Validate if the response amount matches the cart amount
        if (!empty($response['amount'])) {
            if (!$this->validateCartOrderTotalAndCurrency(
                $cart,
                $response['amount']['value'],
                $response['amount']['currency']
            )) {
                $orderNeedsAttention = true;
            }
        }

        // Based on the result code start different payment flows
        switch ($resultCode) {
            case 'Authorised':
                $orderStatus = \Configuration::get('PS_OS_PAYMENT');
                if ($orderNeedsAttention) {
                    $orderStatus = \Configuration::get('ADYEN_OS_PAYMENT_NEEDS_ATTENTION');
                }

                $this->createOrUpdateOrder($cart, $extraVars, $customer, $orderStatus);

                $newOrder = new \Order((int)$this->module->currentOrder);

                if (array_key_exists('additionalData', $response)) {
                    $this->orderService->addPaymentDataToOrderFromResponse($newOrder, $response['additionalData']);
                }

                // PaymentResponse can be deleted
                $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

                $this->redirectUserToPageLink(
                    $this->context->link->getPageLink(
                        'order-confirmation',
                        $this->ssl,
                        null,
                        sprintf(
                            "id_cart=%s&id_module=%s&id_order=%s&key=%s",
                            $cart->id,
                            $this->module->id,
                            $this->module->currentOrder,
                            $customer->secure_key
                        )
                    ),
                    $isAjax
                );

                break;
            case 'Refused':
            case 'Cancelled':
                // PaymentResponse can be deleted
                $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

                if ($cart->OrderExists() !== false) {
                    $order = $this->orderAdapter->getOrderByCartId($cart->id);
                    if (\Validate::isLoadedObject($order)) {
                        $this->createOrUpdateOrder($cart, $extraVars, $customer, \Configuration::get('PS_OS_CANCELED'));
                    } else {
                        $this->logger->addError('Order cannot be loaded for cart id: ' . $cart->id);
                    }
                }

                // In case of refused/cancelled payment there is no order created and the cart needs to be cloned and
                // reinitiated
                $this->cartService->cloneCurrentCart($this->context, $cart, $this->versionChecker->isPrestaShop16());
                $this->logger->error('The payment was ' . \Tools::strtolower($resultCode) . ', with cart id:  ' .
                    $cart->id);

                if ($resultCode === 'Cancelled') {
                    $message = $this->module->l('The payment was cancelled by the customer');
                } else {
                    $message = $this->module->l('The payment was refused');
                }

                if ($isAjax) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => $message
                            )
                        )
                    );
                } else {
                    if ($this->versionChecker->isPrestaShop16()) {
                        $this->setTemplate(
                            $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
                        );
                    } else {
                        $this->redirectUserToPageLink(
                            $this->context->link->getPageLink(
                                'order',
                                $this->ssl,
                                null,
                                sprintf('message=%s', $message)
                            ),
                            $isAjax
                        );
                    }
                }

                break;
            case 'RedirectShopper':
                // orderStatusId used to not send the order_conf email
                $extraVars['orderStatusId'] = \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT');
                // Create an order for each redirectShopper payments with the state of ADYEN_OS_WAITING_FOR_PAYMENT
                $this->createOrUpdateOrder(
                    $cart,
                    $extraVars,
                    $customer,
                    \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT')
                );

                // Handle the rest the same way as the cases below
            case 'IdentifyShopper':
            case 'ChallengeShopper':
            case 'Pending':
                // Store response for cart until the payment is done
                $this->adyenPaymentResponseModel->insertOrUpdatePaymentResponse(
                    $cart->id,
                    $resultCode,
                    $response,
                    $paymentRequestAmount,
                    $paymentRequestCurrency
                );

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'action',
                        array(
                            'response' => $response['action']
                        )
                    )
                );

                break;
            case 'Received':
            case 'PresentToShopper':
                // Store response for cart temporarily until the payment is done
                $this->adyenPaymentResponseModel->insertOrUpdatePaymentResponse(
                    $cart->id,
                    $resultCode,
                    $response,
                    $paymentRequestAmount,
                    $paymentRequestCurrency
                );

                if (\Validate::isLoadedObject($customer)) {
                    $orderStatus = \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT');
                    if ($orderNeedsAttention) {
                        $orderStatus = \Configuration::get('ADYEN_OS_PAYMENT_NEEDS_ATTENTION');
                    }

                    $this->createOrUpdateOrder(
                        $cart,
                        $extraVars,
                        $customer,
                        $orderStatus
                    );

                    $this->redirectUserToPageLink(
                        $this->context->link->getPageLink(
                            'order-confirmation',
                            $this->ssl,
                            null,
                            sprintf(
                                "id_cart=%s&id_module=%s&id_order=%s&key=%s",
                                $cart->id,
                                $this->module->id,
                                $this->module->currentOrder,
                                $customer->secure_key
                            )
                        ),
                        $isAjax
                    );
                }

                break;
            case 'Error':
                $this->createOrUpdateOrder($cart, $extraVars, $customer, \Configuration::get('PS_OS_ERROR'));

                // PaymentResponse can be deleted
                $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

                // In case of refused payment there is no order created and the cart needs to be cloned and reinitiated
                $this->cartService->cloneCurrentCart($this->context, $cart);

                $this->logger->error(
                    "There was an error with the payment method. id:  " . $cart->id .
                    ' Result code "Error" in response: ' . print_r($response, true)
                );

                if ($isAjax) {
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    $message = $this->module->l('There was an error with the payment method, please choose another one');
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => $message,
                            )
                        )
                    );
                } else {
                    $this->setTemplate(
                        $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
                    );
                }

                break;
            default:
                $this->createOrUpdateOrder($cart, $extraVars, $customer, \Configuration::get('PS_OS_ERROR'));

                $this->logger->error(
                    "There was an error with the payment method. id:  " . $cart->id .
                    ' Unsupported result code in response: ' . print_r($response, true)
                );

                if ($isAjax) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => $this->module->l('Unsupported result code:') .
                                    "{" . $response['resultCode'] . "}"
                            )
                        )
                    );
                } else {
                    $this->setTemplate(
                        $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
                    );
                }

                break;
        }
    }

    /**
     * Available in the php api library from version 7.0.0
     *
     * @param array $stateData
     * @return array
     */
    protected function getValidatedAdditionalData($stateData)
    {
        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = self::getArrayOnlyWithApprovedKeys($stateData, $this->stateDataRootKeys);
        }
        return $stateData;
    }

    /**
     * Returns an array with only the approved keys
     * Available in the php api library from version 7.0.0
     *
     * @param array $array
     * @param array $approvedKeys
     * @return array
     */
    protected static function getArrayOnlyWithApprovedKeys($array, $approvedKeys)
    {
        $result = array();

        foreach ($approvedKeys as $approvedKey) {
            if (isset($array[$approvedKey])) {
                $result[$approvedKey] = $array[$approvedKey];
            }
        }
        return $result;
    }

    /**
     * @param $cart
     * @param $extraVars
     * @param $customer
     * @param $orderStatus
     * @throws \PrestaShopException
     */
    private function createOrUpdateOrder($cart, $extraVars, $customer, $orderStatus)
    {
        // Load order if exists from cart id
        if ($cart->OrderExists() !== false) {
            $order = $this->orderAdapter->getOrderByCartId($cart->id);
            if (\Validate::isLoadedObject($order)) {
                $this->orderService->updateOrderState($order, $orderStatus);
                $orderPayment = $this->orderPaymentService->getAdyenOrderPayment($order);
                if ($orderPayment && array_key_exists('transaction_id', $extraVars)) {
                    $this->orderPaymentService->addPspReferenceForOrderPayment(
                        $orderPayment,
                        $extraVars['transaction_id']
                    );
                }
            } else {
                $this->logger->addError('Order cannot be loaded for cart id: ' . $cart->id);
            }
        } else {
            $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
            $this->module->validateOrder(
                $cart->id,
                $orderStatus,
                $total,
                $this->module->displayName,
                null,
                $extraVars,
                (int)$cart->id_currency,
                false,
                $customer->secure_key
            );
        }
    }

    /**
     * Returns true in case the payment value and currency matches the cart
     *
     * @param \Cart $cart
     * @param $amount
     * @param $currency
     * @return bool
     * @throws \Exception
     */
    protected function validateCartOrderTotalAndCurrency(\Cart $cart, $amount, $currency)
    {
        $cartCurrency = \Currency::getCurrency($cart->id_currency);
        $cartCurrencyIso = $cartCurrency['iso_code'];

        $orderTotalInMinorUnits = $this->utilCurrency->sanitize(
            $cart->getOrderTotal(true, \Cart::BOTH),
            $cartCurrencyIso
        );

        // In case amount or currency doesn't match return false
        if ((int)$amount !== $orderTotalInMinorUnits || $currency !== $cartCurrencyIso) {
            $this->logger->addWarning(
                'The cart (id: "' . $cart->id . '") amount ("' . $orderTotalInMinorUnits . '") or currency ("' .
                $cartCurrencyIso . '") has changed during the payment process from amount ("' . $amount .
                '") or currency ("' . $currency . '"). The order has not been placed but the customer was shown an ' .
                'error message'
            );
            return false;
        }

        return true;
    }
}
