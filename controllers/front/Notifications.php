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

use Adyen\PrestaShop\controllers\FrontController;
use Adyen\PrestaShop\service\notification\NotificationReceiver;

class AdyenNotificationsModuleFrontController extends FrontController
{

    /**
     * AdyenNotificationsModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $notificationReceiver = new NotificationReceiver(
            $this->helperData,
            new \Adyen\Util\HmacSignature(),
            Configuration::get('ADYEN_NOTI_HMAC'),
            Configuration::get('ADYEN_MERCHANT_ACCOUNT'),
            Configuration::get('ADYEN_NOTI_USERNAME'),
            Configuration::get('ADYEN_NOTI_PASSWORD'),
            Db::getInstance()
        );
        try {
            die($notificationReceiver->doPostProcess(
                json_decode(file_get_contents('php://input'), true))
            );
        } catch (\Adyen\PrestaShop\service\notification\AuthenticationException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (\Adyen\PrestaShop\service\notification\HMACKeyValidationException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (\Adyen\PrestaShop\service\notification\MerchantAccountCodeException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (\Adyen\AdyenException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (PrestaShopDatabaseException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            die(json_encode(['success' => false, 'message' => "Database error \n{$e->getMessage()}"]));
        } catch (\Adyen\PrestaShop\service\notification\AuthorizationException $e) {
            header('HTTP/1.1 401 Unauthorized', true, 401);
            header('Status: 401 Unauthorized');
            $this->helperData->adyenLogger()->logError($e->getMessage());
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
}
