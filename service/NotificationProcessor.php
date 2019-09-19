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

namespace Adyen\PrestaShop\service;

use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\service\notification\AuthenticationException;
use Adyen\PrestaShop\service\notification\AuthorizationException;
use Adyen\PrestaShop\service\notification\HMACKeyValidationException;
use Adyen\PrestaShop\service\notification\MerchantAccountCodeException;
use Adyen\Util\HmacSignature;
use Db;

class NotificationProcessor
{
    /**
     * @var AdyenHelper
     */
    private $helperData;

    /**
     * @var string
     */
    private $notificationHMAC;

    /**
     * @var HmacSignature
     */
    private $hmacSignature;

    /**
     * @var string
     */
    private $merchantAccount;

    /**
     * @var string
     */
    private $notificationUsername;

    /**
     * @var string
     */
    private $notificationPassword;

    /**
     * @var Db
     */
    private $dbInstance;

    /**
     * NotificationProcessor constructor.
     * @param AdyenHelper $helperData
     * @param HmacSignature $hmacSignature
     * @param $notificationHMAC
     * @param $merchantAccount
     * @param $notificationUsername
     * @param $notificationPassword
     * @param Db $dbInstance
     */
    public function __construct(
        AdyenHelper $helperData,
        HmacSignature $hmacSignature,
        $notificationHMAC,
        $merchantAccount,
        $notificationUsername,
        $notificationPassword,
        Db $dbInstance
    ) {
        $this->helperData = $helperData;
        $this->hmacSignature = $hmacSignature;
        $this->notificationHMAC = $notificationHMAC;
        $this->merchantAccount = $merchantAccount;
        $this->notificationUsername = $notificationUsername;
        $this->notificationPassword = $notificationPassword;
        $this->dbInstance = $dbInstance;
    }

    /**
     * @param $notificationItems
     * @return false|string|null
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws MerchantAccountCodeException
     * @throws \Adyen\AdyenException
     * @throws \PrestaShopDatabaseException
     * @throws AuthorizationException
     */
    public function doPostProcess($notificationItems)
    {
        $notificationMode = isset($notificationItems['live']) ? $notificationItems['live'] : '';

        if ($notificationMode !== '' && $this->validateNotificationMode($notificationMode)) {

            $acceptedMessage = '[accepted]';

            foreach ($notificationItems['notificationItems'] as $notificationItem) {
                if (!$this->processNotification($notificationItem['NotificationRequestItem'])) {
                    throw new AuthorizationException();
                }
            }
            $cronCheckTest = $notificationItems['notificationItems'][0]['NotificationRequestItem']['pspReference'];

            // Run the query for checking unprocessed notifications, do this only for test notifications coming from
            // the Adyen Customer Area
            if ($this->isTestNotification($cronCheckTest)) {
                $unprocessedNotifications = $this->getUnprocessedNotifications();
                if ($unprocessedNotifications > 0) {
                    $acceptedMessage .= "\nYou have $unprocessedNotifications unprocessed notifications.";
                }
            }

            $this->helperData->adyenLogger()->logDebug('The result is accepted');
            return $this->returnAccepted($acceptedMessage);
        } else {
            if ($notificationMode == '') {
                throw new AuthorizationException();
            }
            $message = 'Mismatch between Live/Test modes of PrestaShop store and the Adyen platform';
            $this->helperData->adyenLogger()->logError($message);
            return json_encode(
                [
                    'success' => false,
                    'message' => $message
                ]
            );
        }
    }

    /**
     * HTTP Authentication of the notification
     *
     * @param $response
     * @return bool
     * @throws MerchantAccountCodeException
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws \Adyen\AdyenException
     */
    protected function authorised($response)
    {
        $internalMerchantAccount = $this->merchantAccount;
        $submittedMerchantAccount = $response['merchantAccountCode'];

        $isTestNotification = $this->isTestNotification($response['pspReference']);
        if (empty($submittedMerchantAccount) && empty($internalMerchantAccount)) {
            if ($isTestNotification) {
                throw new MerchantAccountCodeException('merchantAccountCode is empty in PrestaShop settings');
            }
            return false;
        }

        // validate username and password
        if ((!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW']))) {
            if ($isTestNotification) {
                $message = 'Authentication failed: PHP_AUTH_USER and PHP_AUTH_PW are empty.';
                $this->helperData->adyenLogger()->logError($message);
                throw new AuthenticationException($message);
            }
            return false;
        }

        // validate hmac


        if (!$this->verifyHmac($response)) {
            $message = 'HMAC key validation failed';
            $this->helperData->adyenLogger()->logError($message);
            throw new HMACKeyValidationException($message);
        }

        $usernameCmp = strcmp($_SERVER['PHP_AUTH_USER'], $this->notificationUsername);
        $passwordCmp = strcmp($_SERVER['PHP_AUTH_PW'], $this->notificationPassword);
        if ($usernameCmp === 0 && $passwordCmp === 0) {
            return true;
        }

        // If notification is test check if fields are correct if not return error
        if ($isTestNotification) {
            if ($usernameCmp != 0 || $passwordCmp != 0) {
                $message = 'username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as PrestaShop settings';
                $this->helperData->adyenLogger()->logError($message);
                throw new AuthenticationException($message);
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
        if (
            ($mode == '1' && ($notificationMode == 'false' || $notificationMode == false)) ||
            ($mode == '0' && ($notificationMode == 'true' || $notificationMode == true))
        ) {
            return true;
        }
        return false;
    }

    /**
     * save notification into the database for cron job to execute notification
     *
     * @param $response
     * @return bool
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws MerchantAccountCodeException
     * @throws \Adyen\AdyenException
     * @throws \PrestaShopDatabaseException
     */
    protected function processNotification($response)
    {
        // validate the notification
        if ($this->authorised($response)) {

            // log the notification
            $this->helperData->adyenLogger()->logDebug(
                'The content of the notification item is: ' . print_r($response, 1)
            );

            // check if notification already exists
            if (!$this->isTestNotification($response['pspReference']) && !$this->isDuplicate($response)) {
                $this->insertNotification($response);
                return true;
            } else {
                // duplicated so do nothing but return accepted to Adyen
                $this->helperData->adyenLogger()->logDebug('Notification is a TEST notification from Adyen Customer Area');
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
        if (strpos(strtolower($pspReference), 'test_') !== false
            || strpos(strtolower($pspReference), 'testnotification_') !== false
        ) {
            return true;
        } else {
            return false;
        }
    }

    private function returnAccepted($acceptedMessage)
    {
        if (empty($acceptedMessage)) {
            $acceptedMessage = '[accepted]';
        }
        return $acceptedMessage;
    }

    /**
     * @param array $notification
     * @throws \PrestaShopDatabaseException
     */
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

        $this->dbInstance->insert(
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
        if (!empty($response['originalReference'])) {
            $originalReference = trim($response['originalReference']);
            $sql .= ' AND `original_reference` = "' . pSQL($originalReference) . '"';
        }
        $query = $this->dbInstance->getValue($sql);

        return $query;
    }

    protected function getUnprocessedNotifications()
    {
        /** @noinspection SqlDialectInspection */
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'adyen_notification '
            . 'WHERE `done` = "' . (int)0 . '"'
            . ' AND `processing` = "' . (int)0 . '"';

        return $this->dbInstance->getValue($sql);
    }

    /**
     * @param $notification
     * @return bool
     * @throws \Adyen\AdyenException
     */
    private function verifyHmac($notification)
    {
        return $this->hmacSignature->isValidNotificationHMAC($this->notificationHMAC, $notification);
    }
}
