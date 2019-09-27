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
        $adyenHelperFactory = new \Adyen\PrestaShop\service\helper\DataFactory();
        $this->helperData = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );

        $this->helperData->startSession();
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
        // Currently this controller only handles ajax requests
        $this->ajax = true;

        $payload = $_REQUEST;

        if (!empty($_SESSION['paymentData'])) {
            // Add payment data into the request object
            $request = [
                "paymentData" => $_SESSION['paymentData']
            ];
        } else {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'error',
                [
                    'message' => "3D secure 2.0 failed, payment data not found"
                ]
            ));
        }

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
        } else {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'error',
                [
                    'message' => "3D secure 2.0 failed, payload details are not found"
                ]
            ));
        }

        // Send the payments details request
        try {
            $client = $this->helperData->initializeAdyenClient();

            // call lib
            $service = new \Adyen\Service\Checkout($client);

            $result = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'error',
                [
                    'message' => '3D secure 2.0 failed'
                ]
            ));
        }

        // Check if result is challenge shopper, if yes return the token
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'ChallengeShopper' &&
            !empty($result['authentication']['threeds2.challengeToken'])
        ) {
            $this->ajaxRender($this->helperData->buildControllerResponseJson(
                'threeDS2',
                [
                    'type' => $result['resultCode'],
                    'token' => $result['authentication']['threeds2.challengeToken']
                ]
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
