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
use Adyen\PrestaShop\service\Adyen\Service\NotificationProcessor;

class AdyenNotificationsModuleFrontController extends FrontController
{

    /**
     * AdyenNotificationsModuleFrontController constructor.
     * @throws \Adyen\AdyenException
     */
    public function __construct()
    {
        parent::__construct();
        $adyenHelperFactory = new \Adyen\PrestaShop\service\Adyen\Helper\DataFactory();
        $this->helperData = $adyenHelperFactory->createAdyenHelperData(
            Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );
        $this->helperData->startSession();
    }


    /**
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $notificationProcessor = new NotificationProcessor(
            $this->helperData,
            new \Adyen\Util\HmacSignature(),
            Configuration::get('ADYEN_NOTI_HMAC'),
            Configuration::get('ADYEN_MERCHANT_ACCOUNT'),
            Configuration::get('ADYEN_NOTI_USERNAME'),
            Configuration::get('ADYEN_NOTI_PASSWORD'),
            Db::getInstance()
        );
        try {
            $this->ajaxRender($notificationProcessor->doPostProcess(
                json_decode(file_get_contents('php://input'), true))
            );
        } catch (\Adyen\PrestaShop\service\Adyen\Service\Notification\AuthenticationException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxRender(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (\Adyen\PrestaShop\service\Adyen\Service\Notification\HMACKeyValidationException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxRender(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (\Adyen\PrestaShop\service\Adyen\Service\Notification\MerchantAccountCodeException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxRender(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (\Adyen\AdyenException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxRender(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (PrestaShopDatabaseException $e) {
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxRender(json_encode(['success' => false, 'message' => "Database error \n{$e->getMessage()}"]));
        } catch (\Adyen\PrestaShop\service\Adyen\Service\Notification\AuthorizationException $e) {
            header('HTTP/1.1 401 Unauthorized', true, 401);
            header('Status: 401 Unauthorized');
            $this->helperData->adyenLogger()->logError($e->getMessage());
            $this->ajaxRender(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }



}