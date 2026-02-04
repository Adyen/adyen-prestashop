<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenNotificationsController
 */
class AdyenNotificationsController extends AdyenBaseController
{
    /**
     * @return void
     */
    public function displayAjaxGetNotifications(): void
    {
        $storeId = Tools::getValue('storeId');
        $page = Tools::getValue('page');
        $limit = Tools::getValue('limit');

        $result = AdminAPI::get()->shopNotifications($storeId)->getNotifications($page, $limit);

        if (!$result->isSuccessful()) {
            AdyenPrestaShopUtility::dieJson($result);

            return;
        }

        $jsonResponse = $result->toArray();
        $map = $this->mapOrderNumbers($this->getMerchantReferences($jsonResponse['notifications']));

        foreach ($jsonResponse['notifications'] as $key => $item) {
            $jsonResponse['notifications'][$key]['orderId'] = (string) $map[$item['orderId']];
        }

        AdyenPrestaShopUtility::dieJsonArray($jsonResponse);
    }

    /**
     * @param array $logs
     *
     * @return array
     */
    private function getMerchantReferences(array $logs): array
    {
        return array_unique(
            array_map(static function (array $log) {
                return $log['orderId'];
            }, $logs)
        );
    }

    /**
     * @param string[] $references
     *
     * @return array
     */
    private function mapOrderNumbers(array $references): array
    {
        if (empty($references)) {
            return [];
        }

        $orderNumbers = [];
        foreach ($references as $reference) {
            $orderNumbers[$reference] = Order::getIdByCartId((int) $reference);
        }

        return $orderNumbers;
    }
}
