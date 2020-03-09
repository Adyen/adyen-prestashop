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
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\Cart as CartService;
use Adyen\PrestaShop\model\AdyenPaymentResponse;

abstract class FrontController extends \ModuleFrontController
{
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
     * @var OrderAdapter
     */
    protected $orderPaymentAdapter;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var AdyenPaymentResponse
     */
    protected $adyenPaymentResponseModel;

    /**
     * FrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->versionChecker = ServiceLocator::get('Adyen\PrestaShop\application\VersionChecker');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        $this->orderPaymentAdapter = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\order\OrderPaymentAdapter');
        $this->cartService = ServiceLocator::get('Adyen\PrestaShop\service\Cart');
        $this->adyenPaymentResponseModel = ServiceLocator::get('Adyen\PrestaShop\model\AdyenPaymentResponse');
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
        if ($this->versionChecker->isPrestaShop16()) {
            $this->ajax = true;
            parent::ajaxDie($value, $controller, $method);
        } else {
            parent::ajaxRender($value, $controller, $method);
            exit;
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
     * @throws AdyenException
     */
    protected function handlePaymentsResponse($response, $cart, $customer, $isAjax)
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
                    2,
                    $total,
                    $this->module->displayName,
                    null,
                    $extraVars,
                    (int)$cart->id_currency,
                    false,
                    $customer->secure_key
                );

                $newOrder = new \Order((int)$this->module->currentOrder);

                if (\Validate::isLoadedObject($newOrder)) {
                    // Save available data into the order_payment table
                    $paymentCollection = $newOrder->getOrderPaymentCollection();
                    foreach ($paymentCollection as $payment) {
                        $cardSummary = !empty($response['additionalData']['cardSummary'])
                            ? pSQL($response['additionalData']['cardSummary'])
                            : '****';
                        $cardBin = !empty($response['additionalData']['cardBin'])
                            ? pSQL($response['additionalData']['cardBin'])
                            : '******';
                        $paymentMethod = !empty($response['additionalData']['paymentMethod'])
                            ? pSQL($response['additionalData']['paymentMethod'])
                            : 'Adyen';
                        $expiryDate = !empty($response['additionalData']['expiryDate'])
                            ? pSQL($response['additionalData']['expiryDate'])
                            : '';
                        $cardHolderName = !empty($response['additionalData']['cardHolderName'])
                            ? pSQL($response['additionalData']['cardHolderName']) : '';
                        $payment->card_number = $cardBin . ' *** ' . $cardSummary;
                        $payment->card_brand = $paymentMethod;
                        $payment->card_expiration = $expiryDate;
                        $payment->card_holder = $cardHolderName;
                        $payment->save();
                    }
                }

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
                // In case of refused payment there is no order created and the cart needs to be cloned and reinitiated
                $this->cartService->cloneCurrentCart($this->context, $cart);
                $this->logger->error("The payment was refused, id:  " . $cart->id);

                if ($isAjax) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => "The payment was refused"
                            )
                        )
                    );
                } else {
                    $this->setTemplate(
                        $this->helperData->getTemplateFromModulePath('views/templates/front/error.tpl')
                    );
                }

                break;
            case 'IdentifyShopper':
                // Store response for cart until the payment is done
                $this->adyenPaymentResponseModel->insertPaymentResponse($cart->id, $resultCode, $response);

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'threeDS2',
                        array(
                            'type' => 'IdentifyShopper',
                            'token' => $response['authentication']['threeds2.fingerprintToken']
                        )
                    )
                );

                break;
            case 'ChallengeShopper':
                // Store response for cart temporarily until the payment is done
                $this->adyenPaymentResponseModel->insertPaymentResponse($cart->id, $resultCode, $response);

                $this->ajaxRender(
                    $this->helperData->buildControllerResponseJson(
                        'threeDS2',
                        array(
                            'type' => 'ChallengeShopper',
                            'token' => $response['authentication']['threeds2.challengeToken']
                        )
                    )
                );
                break;
            case 'RedirectShopper':
                // Check if redirect shopper response data is valid
                if (empty($response['redirect']['url']) ||
                    empty($response['redirect']['method']) ||
                    empty($response['paymentData'])
                ) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => $this->l(
                                    "There was an error with the payment method, please choose another one."
                                )
                            )
                        )
                    );
                }

                // Store response for cart temporarily until the payment is done
                $this->adyenPaymentResponseModel->insertPaymentResponse($cart->id, $resultCode, $response);

                $this->context->cookie->__set("id_cart", "");

                $redirectUrl = $response['redirect']['url'];
                $redirectMethod = $response['redirect']['method'];

                // Identify if 3DS1 redirect
                if (!empty($response['redirect']['data']['PaReq']) && !empty($response['redirect']['data']['MD'])) {
                    $paRequest = $response['redirect']['data']['PaReq'];
                    $md = $response['redirect']['data']['MD'];

                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'threeDS1',
                            array(
                                'paRequest' => $paRequest,
                                'md' => $md,
                                'issuerUrl' => $redirectUrl,
                                'redirectMethod' => $redirectMethod,
                                'reference' => $cart->id
                            )
                        )
                    );
                } else {
                    $this->redirectUserToPageLink($redirectUrl, $isAjax);
                }

                break;
            case 'PresentToShopper':
                // Store response for cart temporarily until the payment is done
                $this->adyenPaymentResponseModel->insertPaymentResponse($cart->id, $resultCode, $response);

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
                    8,
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
                    ' Result code "Error" in response: ' . print_r($response, true)
                );

                if ($isAjax) {
                    $this->ajaxRender(
                        $this->helperData->buildControllerResponseJson(
                            'error',
                            array(
                                'message' => $this->l(
                                    "There was an error with the payment method, please choose another one."
                                )
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
                    8,
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
                                'message' => $this->l("Unsupported result code:") . "{" . $response['resultCode'] . "}"
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
     * @return mixed
     */
    protected function handle3DS1()
    {
        $cart = $this->getCurrentCart();

        $paRequest = \Tools::getValue(\AdyenPaymentModuleFrontController::PA_REQUEST);
        $md = \Tools::getValue(\AdyenPaymentModuleFrontController::MD);
        $issuerUrl = \Tools::getValue(\AdyenPaymentModuleFrontController::ISSUER_URL);
        $redirectMethod = \Tools::getValue(\AdyenPaymentModuleFrontController::REDIRECT_METHOD);
        $reference = \Tools::getValue(\AdyenPaymentModuleFrontController::REFERENCE);

        $termUrl = $this->context->link->getModuleLink(
            "adyen",
            'Validate3d',
            array('reference' => $reference),
            true
        );

        $this->context->smarty->assign(
            array(
                'paRequest' => $paRequest,
                'md' => $md,
                'issuerUrl' => $issuerUrl,
                'redirectMethod' => $redirectMethod,
                'termUrl' => $termUrl
            )
        );

        return $this->setTemplate(
            $this->helperData->getTemplateFromModulePath('views/templates/front/redirect.tpl')
        );
    }

    /**
     * @return bool
     */
    protected function is3DS1Process()
    {
        $paRequest = \Tools::getValue(\AdyenPaymentModuleFrontController::PA_REQUEST);
        $md = \Tools::getValue(\AdyenPaymentModuleFrontController::MD);
        $issuerUrl = \Tools::getValue(\AdyenPaymentModuleFrontController::ISSUER_URL);
        $redirectMethod = \Tools::getValue(\AdyenPaymentModuleFrontController::REDIRECT_METHOD);
        $reference = \Tools::getValue(\AdyenPaymentModuleFrontController::REFERENCE);

        if (!empty($paRequest) &&
            !empty($md) &&
            !empty($issuerUrl) &&
            !empty($redirectMethod) &&
            !empty($reference)
        ) {
            return true;
        }

        return false;
    }
}
