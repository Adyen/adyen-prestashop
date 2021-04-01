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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\service\notification;

use Adyen\AdyenException;
use Adyen\PrestaShop\helper\Data as AdyenHelper;
use Adyen\PrestaShop\infra\AsyncNotificationTrigger;
use Adyen\PrestaShop\infra\Crypto;
use Adyen\PrestaShop\model\AdyenNotification;
use Adyen\PrestaShop\service\adapter\classes\Configuration;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\Util\HmacSignature;
use Db;
use Psr\Log\LoggerInterface;

class NotificationReceiver
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var AdyenNotification
     */
    private $adyenNotification;

    /**
     * NotificationReceiver constructor.
     *
     * @param AdyenHelper $helperData
     * @param HmacSignature $hmacSignature
     * @param $notificationHMAC
     * @param $merchantAccount
     * @param $notificationUsername
     * @param $notificationPassword
     * @param Db $dbInstance
     * @param LoggerInterface $logger
     * @param Configuration $configuration
     * @param AdyenNotification $adyenNotification
     */
    public function __construct(
        AdyenHelper $helperData,
        HmacSignature $hmacSignature,
        $notificationHMAC,
        $merchantAccount,
        $notificationUsername,
        $notificationPassword,
        Db $dbInstance,
        LoggerInterface $logger,
        Configuration $configuration,
        AdyenNotification $adyenNotification
    ) {
        $this->helperData = $helperData;
        $this->hmacSignature = $hmacSignature;
        $this->notificationHMAC = $notificationHMAC;
        $this->merchantAccount = $merchantAccount;
        $this->notificationUsername = $notificationUsername;
        $this->notificationPassword = $notificationPassword;
        $this->dbInstance = $dbInstance;
        $this->logger = $logger;
        $this->configuration = $configuration;
        $this->adyenNotification = $adyenNotification;
    }

    /**
     * @param $notificationItems
     * @return false|string|null
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws MerchantAccountCodeException
     * @throws AdyenException
     * @throws \PrestaShopDatabaseException
     * @throws AuthorizationException
     */
    public function doPostProcess($notificationItems)
    {
        if (empty($notificationItems)) {
            $message = 'Notification is not formatted correctly';
            $this->logger->addAdyenNotification($message);
            return json_encode(
                array(
                    'success' => false,
                    'message' => $message
                )
            );
        }

        if (!empty($notificationItems['live']) && $this->validateNotificationMode($notificationItems['live'])) {
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
                $unprocessedNotifications = $this->adyenNotification->getNumberOfUnprocessedNotifications();
                if ($unprocessedNotifications > 0) {
                    $acceptedMessage .= "\nYou have $unprocessedNotifications unprocessed notifications.";
                }
            }

            if ($this->configuration->isAutoCronjobRunnerEnabled()) {
                $this->callAsyncCronRunner();
            }

            $this->logger->addAdyenNotification('The result is accepted');
            return $this->returnAccepted($acceptedMessage);
        } else {
            $message = 'Mismatch between Live/Test modes of PrestaShop store and the Adyen platform';
            $this->logger->addAdyenNotification($message);
            return json_encode(
                array(
                    'success' => false,
                    'message' => $message
                )
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
     * @throws AdyenException
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
        if ((!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']))) {
            if ($isTestNotification) {
                $message = 'Authentication failed: PHP_AUTH_USER and/or PHP_AUTH_PW are empty.';
                $this->logger->addAdyenNotification($message);
                throw new AuthenticationException($message);
            }
            return false;
        }

        // validate hmac
        if (!$this->hmacSignature->isValidNotificationHMAC($this->notificationHMAC, $response)) {
            $message = 'HMAC key validation failed';
            $this->logger->addAdyenNotification($message);
            throw new HMACKeyValidationException($message);
        }

        $usernameIsValid = hash_equals($this->notificationUsername, $_SERVER['PHP_AUTH_USER']);
        $passwordIsValid = hash_equals($this->notificationPassword, $_SERVER['PHP_AUTH_PW']);
        if ($usernameIsValid && $passwordIsValid) {
            return true;
        }

        // If notification is test check if fields are correct if not return error
        if ($isTestNotification) {
            if (!$usernameIsValid || !$passwordIsValid) {
                $message = 'username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as PrestaShop' .
                    ' settings';
                $this->logger->addAdyenNotification($message);
                throw new AuthenticationException($message);
            }
        }
        return false;
    }


    /**
     * Checks if notification mode and the store mode configuration matches
     *
     * @param $notificationMode
     * @return bool
     */
    protected function validateNotificationMode($notificationMode)
    {
        $testMode = $this->configuration->isTestMode();

        // Notification mode can be a string or a boolean
        if (($testMode && ($notificationMode == 'false' || $notificationMode == false)) ||
            (!$testMode && ($notificationMode == 'true' || $notificationMode == true))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Save notification into the database for cron job to execute notification
     *
     * @param $notification
     * @return bool
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws MerchantAccountCodeException
     * @throws AdyenException
     */
    protected function processNotification($notification)
    {
        // validate the notification
        if ($this->authorised($notification)) {
            // log the notification
            $this->logger->addAdyenNotification(
                'The content of the notification item is: ' . print_r($notification, 1)
            );

            // skip report notifications
            if ($this->isReportNotification($notification['eventCode'])) {
                $this->logger->addAdyenNotification('Notification is a REPORT notification from Adyen Customer Area');
                return true;
            }

            // check if notification already exists
            if (!$this->isTestNotification($notification['pspReference']) && !$this->adyenNotification->isDuplicate(
                $notification
            )) {
                $this->adyenNotification->insertNotification($notification);
                return true;
            } else {
                // duplicated so do nothing but return accepted to Adyen
                $this->logger->addAdyenNotification('Notification is a TEST notification from Adyen Customer Area');
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
        if (strpos(\Tools::strtolower($pspReference), 'test_') !== false
            || strpos(\Tools::strtolower($pspReference), 'testnotification_') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if notification is a report notification
     *
     * @param $eventCode
     * @return bool
     */
    protected function isReportNotification($eventCode)
    {
        if (strpos($eventCode, 'REPORT_') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Add '[accepted]' into $acceptedMessage if empty
     *
     * @param $acceptedMessage
     * @return string
     */
    private function returnAccepted($acceptedMessage)
    {
        if (empty($acceptedMessage)) {
            $acceptedMessage = '[accepted]';
        }
        return $acceptedMessage;
    }

    private function callAsyncCronRunner()
    {
        try {
            /** @var Crypto $crypto */
            $crypto = ServiceLocator::get('Adyen\PrestaShop\infra\Crypto');
            /** @var AsyncNotificationTrigger $asyncNotificationTrigger */
            $asyncNotificationTrigger = ServiceLocator::get('Adyen\PrestaShop\infra\AsyncNotificationTrigger');
            $asyncNotificationTrigger->trigger(
                $crypto->decrypt(\Configuration::get('ADYEN_ADMIN_PATH')),
                $crypto->decrypt(\Configuration::get('ADYEN_CRONJOB_TOKEN'))
            );
        } catch (\Exception $e) {
            $this->logger->addAdyenNotification(
                'Could not call the cron service',
                array('exception' => $e, 'type' => get_class($e))
            );
        }
    }
}
