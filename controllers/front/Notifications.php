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

use Adyen\PrestaShop\controllers\FrontController;

class AdyenNotificationsModuleFrontController extends FrontController
{

    public function __construct()
    {
        parent::__construct();
        $adyenHelperFactory = new \Adyen\PrestaShop\service\Adyen\Helper\DataFactory();
        $this->helperData = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );
        $this->helperData->startSession();
    }

    public function postProcess()
    {
        $this->doPostProcess();
    }

    /**
     * HTTP Authentication of the notification
     *
     * @param $response
     * @return bool
     */
    protected function authorised($response)
    {

        $internalMerchantAccount = \Configuration::get('ADYEN_MERCHANT_ACCOUNT');
        $username = \Configuration::get('ADYEN_NOTI_USERNAME');
        $password = \Configuration::get('ADYEN_NOTI_PASSWORD');

        $submittedMerchantAccount = $response['merchantAccountCode'];

        if (empty($submittedMerchantAccount) && empty($internalMerchantAccount)) {
            if ($this->isTestNotification($response['pspReference'])) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'merchantAccountCode is empty in Prestashop settings'
                ]));
            }
            return false;
        }

        // validate username and password
        if ((!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW']))) {
            if ($this->isTestNotification($response['pspReference'])) {
                $message = 'Authentication failed: PHP_AUTH_USER and PHP_AUTH_PW are empty.';
                $this->helperData->adyenLogger()->logError($message);
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => $message
                ]));
            }
            return false;
        }

        // validate hmac

        if (!$this->verifyHmac($response)) {
            $message = "HMAC key validation failed";
            $this->helperData->adyenLogger()->logError($message);
            $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => $message
                ])
            );
            return false;
        }

        $usernameCmp = strcmp($_SERVER['PHP_AUTH_USER'], $username);
        $passwordCmp = strcmp($_SERVER['PHP_AUTH_PW'], $password);
        if ($usernameCmp === 0 && $passwordCmp === 0) {
            return true;
        }

        // If notification is test check if fields are correct if not return error
        if ($this->isTestNotification($response['pspReference'])) {
            if ($usernameCmp != 0 || $passwordCmp != 0) {
                $message = 'username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as Prestashop settings';
                $this->helperData->adyenLogger()->logError($message);
                $this->ajaxDie(json_encode([
                        'success' => false,
                        'message' => $message
                    ])
                );
            }
        }
        return false;
    }


    /**
     * @param $notificationMode
     * @return bool
     */
    protected function validateNotificationMode($notificationMode)
    {
        $mode = $this->helperData->isDemoMode();

        // Notification mode can be a string or a boolean
        if (($mode == '1' && ($notificationMode == "false" || $notificationMode == false)) || ($mode == '0' && ($notificationMode == 'true' || $notificationMode == true))) {
            return true;
        }
        return false;
    }

    /**
     * save notification into the database for cronjob to execute notification
     *
     * @param $response
     * @param $notificationMode
     * @return bool
     */
    protected function processNotification($response)
    {
        // validate the notification
        if ($this->authorised($response)) {

            // log the notification
            $this->helperData->adyenLogger()->logDebug(
                "The content of the notification item is: " . print_r($response, 1)
            );

            // check if notification already exists
            if (!$this->isTestNotification($response['pspReference']) && !$this->isDuplicate($response)) {
                try {
                    $this->insertNotification($response);
                    return true;
                } catch (Exception $e) {
                    $this->helperData->adyenLogger()->logError("exception: " . $e->getMessage());
                }
            } else {
                // duplicated so do nothing but return accepted to Adyen
                $this->helperData->adyenLogger()->logDebug("Notification is a TEST notification from Adyen Customer Area");
                return true;
            }
        }
        return false;
    }

    /**
     * If notification is a test notification from Adyen Customer Area
     *
     * @param $pspReference
     * @return bool
     */
    protected function isTestNotification($pspReference)
    {
        if (strpos(strtolower($pspReference), "test_") !== false
            || strpos(strtolower($pspReference), "testnotification_") !== false
        ) {
            return true;
        } else {
            return false;
        }
    }

    private function return401($message = null)
    {
        header('HTTP/1.1 401 Unauthorized', true, 401);
        header('Status: 401 Unauthorized');
        exit($message);
    }

    private function returnAccepted($acceptedMessage)
    {
        if(empty($acceptedMessage)){
            $acceptedMessage = "[accepted]";
        }
        header("", true, 200);
        exit($acceptedMessage);
    }

    private function insertNotification($notification)
    {
        $data = array();
        if (isset($notification['pspReference'])) {
            $data['pspreference'] = pSQL($notification['pspReference']);
        }
        if (isset($notification['originalReference'])) {
            $data['original_reference'] = pSQL($notification['originalReference']);
        }
        if (isset($notification['merchantReference'])) {
            $data['merchant_reference'] = pSQL($notification['merchantReference']);
        }
        if (isset($notification['eventCode'])) {
            $data['event_code'] = pSQL($notification['eventCode']);
        }
        if (isset($notification['success'])) {
            $data['success'] = pSQL($notification['success']);
        }
        if (isset($notification['paymentMethod'])) {
            $data['payment_method'] = pSQL($notification['paymentMethod']);
        }
        if (isset($notification['amount'])) {
            $data['amount_value'] = pSQL($notification['amount']['value']);
            $data['amount_currency'] = pSQL($notification['amount']['currency']);
        }
        if (isset($notification['reason'])) {
            $data['reason'] = pSQL($notification['reason']);
        }

        if (isset($notification['additionalData'])) {
            $data['additional_data'] = pSQL(serialize($notification['additionalData']));
        }
        if (isset($notification['done'])) {
            $data['done'] = pSQL($notification['done']);
        }

        // do this to set both fields in the correct timezone
        $date = new \DateTime();
        $data['created_at'] = $date->format('Y-m-d H:i:s');
        $data['updated_at'] = $date->format('Y-m-d H:i:s');

        Db::getInstance()->insert(
            _DB_PREFIX_ . 'adyen_notification',
            $data,
            false,
            false,
            Db::INSERT,
            false
        );
    }

    /**
     * If notification is already saved ignore it
     *
     * @param $response
     * @return mixed
     */
    protected function isDuplicate($response)
    {
        $pspReference = trim($response['pspReference']);
        $eventCode = trim($response['eventCode']);
        $success = trim($response['success']);

        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'adyen_notification '
            . 'WHERE `pspreference` = "' . pSQL($pspReference) . '"'
            . ' AND `event_code` = "' . pSQL($eventCode) . '"'
            . ' AND `success` = "' . pSQL($success) . '"';

        $originalReference = null;
        if (isset($response['originalReference'])) {
            $originalReference = trim($response['originalReference']);
            $sql .= ' AND `original_reference` = "' . pSQL($originalReference) . '"';
        }
        $query = Db::getInstance()->getValue($sql);

        return $query;
    }

    protected function getUnprocessedNotifications()
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'adyen_notification '
            . 'WHERE `done` = "' . (int)0 . '"'
            . ' AND `processing` = "' . (int)0 . '"';

        return Db::getInstance()->getValue($sql);
    }

    private function verifyHmac($notification)
    {
        $hmac = \Configuration::get('ADYEN_NOTI_HMAC');
        $util = new \Adyen\Util\Util();
        try {
            $valid = $util->isValidNotificationHMAC($notification, $hmac);
        } catch (\Adyen\AdyenException $e){
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ])
            );
            return false;
        }
        return $valid;
    }

    protected function doPostProcess()
    {
        try {
            $notificationItems = json_decode(file_get_contents('php://input'), true);

            $notificationMode = isset($notificationItems['live']) ? $notificationItems['live'] : "";

            if ($notificationMode !== "" && $this->validateNotificationMode($notificationMode)) {

                foreach ($notificationItems['notificationItems'] as $notificationItem) {
                    $status = $this->processNotification(
                        $notificationItem['NotificationRequestItem']
                    );

                    if ($status != true) {
                        $this->return401();
                        return;
                    }

                    $acceptedMessage = "[accepted]";
                }
                $cronCheckTest = $notificationItems['notificationItems'][0]['NotificationRequestItem']['pspReference'];

                // Run the query for checking unprocessed notifications, do this only for test notifications coming from the Adyen Customer Area
                if ($this->isTestNotification($cronCheckTest)) {
                    $unprocessedNotifications = $this->getUnprocessedNotifications();
                    if ($unprocessedNotifications > 0) {
                        $acceptedMessage .= "\nYou have " . $unprocessedNotifications . " unprocessed notifications.";
                    }
                }

                $this->helperData->adyenLogger()->logDebug("The result is accepted");
                $this->returnAccepted($acceptedMessage);
                return;
            } else {
                if ($notificationMode == "") {
                    $this->return401();
                    return;
                }
                $message = 'Mismatch between Live/Test modes of Prestashop store and the Adyen platform';
                $this->helperData->adyenLogger()->logError($message);
                $this->ajaxDie(json_encode([
                        'success' => false,
                        'message' => $message
                    ])
                );
            }
        } catch (Exception $e) {
            $this->helperData->adyenLogger()->logError("exception: " . $e->getMessage());
        }
    }


}