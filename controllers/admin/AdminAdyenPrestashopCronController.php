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

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

class AdminAdyenPrestashopCronController extends \ModuleAdminController
{
    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * @var Adyen\PrestaShop\helper\Data
     */
    private $helperData;

    /**
     * @var Adyen\PrestaShop\service\Logger
     */
    private $logger;

    /**
     * @var Adyen\PrestaShop\infra\Crypto
     */
    private $crypto;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function __construct()
    {
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        $this->crypto = ServiceLocator::get('Adyen\PrestaShop\infra\Crypto');

        $cronjobToken = '';

        try {
            $cronjobToken = $this->crypto->decrypt(Configuration::get('ADYEN_CRONJOB_TOKEN'));
        } catch (\Adyen\PrestaShop\exception\GenericLoggedException $e) {
            $this->logger->error(
                'For configuration "ADYEN_CRONJOB_TOKEN" an exception was thrown: ' . $e->getMessage()
            );
        } catch (\Adyen\PrestaShop\exception\MissingDataException $e) {
            $this->logger->debug(
                'The configuration "ADYEN_CRONJOB_TOKEN" has no value set, please add a secure token!'
            );
        }

        if (\Tools::getValue('token') != $cronjobToken) {
            die('Invalid token');
        }

        $this->context = \Context::getContext();

        parent::__construct();
        $this->postProcess();
        die();
    }

    /**
     *
     */
    public function postProcess()
    {
        $notificationProcessor = new \Adyen\PrestaShop\service\notification\NotificationProcessor(
            $this->helperData,
            Db::getInstance(),
            new \Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter(),
            new \Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter(),
            $this->logger,
            Context::getContext()
        );

        $notificationModel = new \Adyen\PrestaShop\model\AdyenNotification();

        $unprocessedNotifications = $notificationProcessor->getUnprocessedNotifications();

        foreach ($unprocessedNotifications as $unprocessedNotification) {
            // update as processing
            $notificationModel->updateNotificationAsProcessing($unprocessedNotification['entity_id']);

            if ($notificationProcessor->processNotification($unprocessedNotification)) {
                // processing is done
                $notificationModel->updateNotificationAsDone($unprocessedNotification['entity_id']);
            } else {
                // processing had some error
                $notificationModel->updateNotificationAsNew($unprocessedNotification['entity_id']);
            }
        }
    }
}
