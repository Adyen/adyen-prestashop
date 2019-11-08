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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

class AdyenThreeDSProcessModuleFrontController extends \Adyen\PrestaShop\controllers\FrontController
{
    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * ThreeDSProcess constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->context = \Context::getContext();
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');

        $this->helperData->startSession();
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
        $payload = $_REQUEST;
        if (!empty($_SESSION['paymentData'])) {
            // Add payment data into the request object
            $request = array(
                "paymentData" => $_SESSION['paymentData']
            );
        } else {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'error',
                array(
                    'message' => "3D secure 2.0 failed, payment data not found"
                )
            ));
            return;
        }

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
        } else {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'error',
                array(
                    'message' => "3D secure 2.0 failed, payload details are not found"
                )
            ));
        }

        // Send the payments details request
        try {
            /** @var \Adyen\PrestaShop\service\Checkout $service */
            $service = ServiceLocator::get('Adyen\PrestaShop\service\Checkout');

            $result = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'error',
                array(
                    'message' => '3D secure 2.0 failed'
                )
            )
            );
        } catch (\PrestaShop\PrestaShop\Adapter\CoreException $e) {
            $this->ajaxRender(
                $this->helperData->buildControllerResponseJson(
                    'error',
                    array(
                        'message' => '3D secure 2.0 failed'
                    )
                )
            );
        }

        // Check if result is challenge shopper, if yes return the token
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'ChallengeShopper' &&
            !empty($result['authentication']['threeds2.challengeToken'])
        ) {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'threeDS2',
                array(
                    'type' => $result['resultCode'],
                    'token' => $result['authentication']['threeds2.challengeToken']
                )
            ));
        }

        // Payment can get back to the original flow
        // Save the payments response because we are going to need it during the place order flow
        $_SESSION["paymentsResponse"] = $result;
        if (!empty($_SESSION['paymentData'])) {
            unset($_SESSION['paymentData']);
        }

        // 3DS2 flow is done, original place order flow can continue from frontend
        $this->ajaxRender($this->helperData->buildControllerResponseJson('threeDS2'));
    }
}
