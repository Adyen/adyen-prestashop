<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\Payment\Repositories\PaymentMethodConfigRepository;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Services\ImageHandler;

/**
 * Upgrades module to version 7.5.0. Removes the configured Amazon Pay payment method for all shops.
 *
 * @param AdyenOfficial $module
 *
 * @return bool
 *
 * @throws Exception
 */
function upgrade_module_7_5_0(AdyenOfficial $module): bool
{
    Bootstrap::init();

    foreach (Shop::getShops(false, null, true) as $shopId) {
        try {
            StoreContext::doWithStore(
                (string) $shopId,
                static function () {
                    /** @var PaymentMethodConfigRepository $paymentMethodConfigRepository */
                    $paymentMethodConfigRepository = ServiceRegister::getService(
                        PaymentMethodConfigRepository::class
                    );
                    $amazonPay = $paymentMethodConfigRepository->getPaymentMethodByCode('amazonpay');

                    if ($amazonPay === null) {
                        return;
                    }

                    ImageHandler::removeImage(
                        $amazonPay->getMethodId(),
                        StoreContext::getInstance()->getStoreId()
                    );
                    $paymentMethodConfigRepository->deletePaymentMethodById($amazonPay->getMethodId());
                }
            );
        } catch (Exception $e) {
            Logger::logWarning(
                'Failed to remove Amazon Pay payment method for shop ' . $shopId . ' because ' . $e->getMessage()
            );
        }
    }

    return true;
}
