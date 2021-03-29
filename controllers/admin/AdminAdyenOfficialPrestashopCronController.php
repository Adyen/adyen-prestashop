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
// phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\OrderPaymentService;
use PrestaShop\PrestaShop\Adapter\CoreException;
use Adyen\PrestaShop\exception\GenericLoggedException;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\service\adapter\classes\order\OrderAdapter;
use Adyen\PrestaShop\service\adapter\classes\CustomerThreadAdapter;
use Adyen\PrestaShop\service\notification\NotificationProcessor;
use Adyen\PrestaShop\model\AdyenPaymentResponse;
use Adyen\PrestaShop\model\AdyenNotification;
use \Adyen\PrestaShop\service\Order as OrderService;

require_once _PS_ROOT_DIR_ . '/modules/adyenofficial/vendor/autoload.php';

class AdminAdyenOfficialPrestashopCronController extends \ModuleAdminController
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
     * @throws CoreException
     */
    public function __construct()
    {
        $this->helperData = ServiceLocator::get('Adyen\PrestaShop\helper\Data');
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');
        $this->crypto = ServiceLocator::get('Adyen\PrestaShop\infra\Crypto');

        $cronjobToken = '';

        try {
            $cronjobToken = $this->crypto->decrypt(\Configuration::get('ADYEN_CRONJOB_TOKEN'));
        } catch (GenericLoggedException $e) {
            $this->logger->error(
                'For configuration "ADYEN_CRONJOB_TOKEN" an exception was thrown: ' . $e->getMessage()
            );
        } catch (MissingDataException $e) {
            $this->logger->debug(
                'The configuration "ADYEN_CRONJOB_TOKEN" has no value set, please add a secure token!'
            );
        }

        if (\Tools::getValue('token') != $cronjobToken) {
            die('Invalid token');
        }

        $this->context = \Context::getContext();

        parent::__construct();
        $failedNotifications = $this->postProcess();
        if (empty($failedNotifications)) {
            $message = 'Cron job finished successfully';
        } else {
            $message = sprintf(
                'An error occurred during the execution of the following notifications: %s',
                implode(', ', $failedNotifications)
            );
        }

        die($message);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function postProcess()
    {
        $failedNotifications = array();
        $notificationProcessor = new NotificationProcessor(
            $this->helperData,
            \Db::getInstance(),
            new OrderAdapter(),
            new CustomerThreadAdapter(),
            $this->logger,
            \Context::getContext(),
            new AdyenPaymentResponse(),
            new OrderService(),
            new \Adyen\Util\Currency(),
            new OrderPaymentService()
        );

        $notificationModel = new AdyenNotification();

        $unprocessedNotifications = $notificationModel->getUnprocessedNotifications();

        foreach ($unprocessedNotifications as $unprocessedNotification) {
            // update as processing
            $notificationModel->updateNotificationAsProcessing($unprocessedNotification['entity_id']);

            if ($notificationProcessor->processNotification($unprocessedNotification)) {
                // processing is done
                $notificationModel->updateNotificationAsDone($unprocessedNotification['entity_id']);
            } else {
                // processing had some error
                $notificationModel->updateNotificationAsNew($unprocessedNotification['entity_id']);
                $failedNotifications[] = $unprocessedNotification['entity_id'];
            }
        }

        return $failedNotifications;
    }
}
