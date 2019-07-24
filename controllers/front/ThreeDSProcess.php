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
 * Adyen Prestashop Extension
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

class AdyenThreeDSProcessModuleFrontController extends \ModuleFrontController
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
        $this->helper_data = new \Adyen\PrestaShop\helper\Data();
        $this->ajax = true;

        if (!isset($_SESSION)) {
            session_start();
        }
    }

    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     */
    public function postProcess()
    {
        $payload = $_REQUEST;

        // Init payments/details request
        $result = [];

        $request = [];

        if (!empty($_SESSION['paymentData'])) {
            // Add payment data into the request object
            $request = [
                "paymentData" => $_SESSION['paymentData']
            ];
        } else {
            echo $this->helper_data->buildControllerResponseJson(
                'error',
                [
                    'message' => "3D secure 2.0 failed, payment data not found"
                ]
            );
        }

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
        } else {
            echo $this->helper_data->buildControllerResponseJson(
                'error',
                [
                    'message' => "3D secure 2.0 failed, payload details are not found"
                ]
            );
        }

        // Send the request
        try {
            $client = $this->helper_data->initializeAdyenClient();

            // call lib
            $service = new \Adyen\Service\Checkout($client);

            $result = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            echo $this->helper_data->buildControllerResponseJson(
                'error',
                [
                    'message' => '3D secure 2.0 failed'
                ]
            );
        }

        // Check if result is challenge shopper, if yes return the token
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'ChallengeShopper' &&
            !empty($result['authentication']['threeds2.challengeToken'])
        ) {
            echo $this->helper_data->buildControllerResponseJson(
                'threeDS2',
                [
                    'type' => $result['resultCode'],
                    'token' => $result['authentication']['threeds2.challengeToken']
                ]
            );

            return;
        }

        // Payment can get back to the original flow
        // Save the payments response because we are going to need it during the place order flow
        $_SESSION["paymentsResponse"] = $result;
        if (!empty($_SESSION['paymentData'])) {
            unset($_SESSION['paymentData']);
        }

        // 3DS2 flow is done, original place order flow can continue from frontend
        echo $this->helper_data->buildControllerResponseJson('threeDS2');
        return;
    }
}
