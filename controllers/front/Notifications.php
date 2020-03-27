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

use Adyen\AdyenException;
use Adyen\PrestaShop\controllers\FrontController;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use Adyen\PrestaShop\service\notification\AuthenticationException;
use Adyen\PrestaShop\service\notification\AuthorizationException;
use Adyen\PrestaShop\service\notification\HMACKeyValidationException;
use Adyen\PrestaShop\service\notification\MerchantAccountCodeException;
use Adyen\PrestaShop\service\notification\NotificationReceiver;
use Adyen\Util\HmacSignature;

class AdyenOfficialNotificationsModuleFrontController extends FrontController
{
    /**
     * AdyenNotificationsModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function postProcess()
    {
        $crypto = ServiceLocator::get('\Adyen\PrestaShop\infra\Crypto');

        $hmacKey = $crypto->decrypt(\Configuration::get('ADYEN_NOTI_HMAC'));
        $notificationPassword = $crypto->decrypt(\Configuration::get('ADYEN_NOTI_PASSWORD'));

        $notificationReceiver = new NotificationReceiver(
            $this->helperData,
            new HmacSignature(),
            $hmacKey,
            \Configuration::get('ADYEN_MERCHANT_ACCOUNT'),
            \Configuration::get('ADYEN_NOTI_USERNAME'),
            $notificationPassword,
            \Db::getInstance(),
            ServiceLocator::get('Adyen\PrestaShop\service\Logger'),
            ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Configuration'),
            ServiceLocator::get('Adyen\PrestaShop\model\AdyenNotification')
        );

        try {
            die(
                $notificationReceiver->doPostProcess(
                    json_decode(\Tools::file_get_contents('php://input'), true)
                )
            );
        } catch (AuthenticationException $e) {
            $this->logger->error($e->getMessage());
            die(json_encode(array('success' => false, 'message' => $e->getMessage())));
        } catch (HMACKeyValidationException $e) {
            $this->logger->error($e->getMessage());
            die(json_encode(array('success' => false, 'message' => $e->getMessage())));
        } catch (MerchantAccountCodeException $e) {
            $this->logger->error($e->getMessage());
            die(json_encode(array('success' => false, 'message' => $e->getMessage())));
        } catch (AdyenException $e) {
            $this->logger->error($e->getMessage());
            die(json_encode(array('success' => false, 'message' => $e->getMessage())));
        } catch (PrestaShopDatabaseException $e) {
            $this->logger->error($e->getMessage());
            die(json_encode(array('success' => false, 'message' => "Database error \n{$e->getMessage()}")));
        } catch (AuthorizationException $e) {
            header('HTTP/1.1 401 Unauthorized', true, 401);
            header('Status: 401 Unauthorized');
            $this->logger->error($e->getMessage());
            die(json_encode(array('success' => false, 'message' => $e->getMessage())));
        }
    }
}
