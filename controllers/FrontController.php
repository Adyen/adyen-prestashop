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

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\Logger;
use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\AdyenException;
use Adyen\PrestaShop\service\Cart as CartService;
use Adyen\PrestaShop\model\AdyenPaymentResponse;
use Adyen\PrestaShop\service\Order as OrderService;

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
     * @param $cart
     * @param $customer
     * @param $isAjax
     * @param $cancelled
     * @throws AdyenException
     */
    protected function handlePaymentsResponse($response, $cart, $customer, $isAjax, $cancelled = false)
    {
        $resultCode = $response['resultCode'];

        $extraVars = array();
        if (!empty($response['pspReference'])) {
            $extraVars['transaction_id'] = $response['pspReference'];
        }

        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);

        // Based on the result code start different payment flows
        switch ($resultCode) {
            case 'Authorised':
                $this->module->validateOrder(
                    $cart->id,
                    \Configuration::get('PS_OS_PAYMENT'),
                    $total,
                    $this->module->displayName,
                    null,
                    $extraVars,
                    (int)$cart->id_currency,
                    false,
                    $customer->secure_key
                );

                $newOrder = new \Order((int)$this->module->currentOrder);

                $this->orderService->addPaymentDataToOrderFromResponse($newOrder, $response);
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

                // In case of refused/cancelled payment there is no order created and the cart needs to be cloned and
            // reinitiated
                $this->cartService->cloneCurrentCart($this->context, $cart);
                $this->logger->error('The payment was ' . strtolower($resultCode) . ', id:  ' . $cart->id);

                if ($cancelled) {
                    $message = $this->l('The payment was cancelled by the customer');
                } else {
                    $message = $this->l('The payment was refused');
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
                    $this->setTemplate(
                        $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
                    );
                }

                break;
            case 'RedirectShopper':
                // When the resultCode is RedirectShopper the cart needs to be cleared
                $this->context->cookie->__set("id_cart", "");
                // Continue with the same logic as IdentifyShopper and ChallengeShopper
            case 'IdentifyShopper':
            case 'ChallengeShopper':
            case 'Pending':
                // Store response for cart until the payment is done
                $this->adyenPaymentResponseModel->insertOrUpdatePaymentResponse($cart->id, $resultCode, $response);

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
                $this->adyenPaymentResponseModel->insertOrUpdatePaymentResponse($cart->id, $resultCode, $response);

                if (\Validate::isLoadedObject($customer)) {
                    $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
                    $extraVars = array();

                    $this->module->validateOrder(
                        $cart->id,
                        \Configuration::get('ADYEN_OS_WAITING_FOR_PAYMENT'),
                        $total,
                        $this->module->displayName,
                        null,
                        $extraVars,
                        $cart->id_currency,
                        false,
                        $customer->secure_key
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
                $this->module->validateOrder(
                    $cart->id,
                    \Configuration::get('PS_OS_ERROR'),
                    $total,
                    $this->module->displayName,
                    null,
                    $extraVars,
                    (int)$cart->id_currency,
                    false,
                    $customer->secure_key
                );

                // PaymentResponse can be deleted
                $this->adyenPaymentResponseModel->deletePaymentResponseByCartId($cart->id);

                // In case of refused payment there is no order created and the cart needs to be cloned and reinitiated
                $this->cartService->cloneCurrentCart($this->context, $cart);

                $this->logger->error(
                    "There was an error with the payment method. id:  " . $cart->id .
                    ' Result code "Error" in response: ' . print_r($response, true)
                );

                if ($isAjax) {
                    $message = $this->l('There was an error with the payment method, please choose another one');
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
                // Unsupported result code
                $this->module->validateOrder(
                    $cart->id,
                    \Configuration::get('PS_OS_ERROR'),
                    $total,
                    $this->module->displayName,
                    null,
                    $extraVars,
                    (int)$cart->id_currency,
                    false,
                    $customer->secure_key
                );

                $this->logger->error(
                    "There was an error with the payment method. id:  " . $cart->id .
                    ' Unsupported result code in response: ' . print_r($response, true)
                );

                if ($isAjax) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => $this->l('Unsupported result code:') . "{" . $response['resultCode'] . "}"
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
}
