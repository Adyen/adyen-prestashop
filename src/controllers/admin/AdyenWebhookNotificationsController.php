<?php

use Adyen\Core\BusinessLogic\AdminAPI\AdminAPI;
use Adyen\Core\BusinessLogic\Domain\Integration\Order\OrderService;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ServiceRegister;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/adyenofficial/vendor/autoload.php';

/**
 * Class AdyenWebhookNotificationsController
 */
class AdyenWebhookNotificationsController extends AdyenBaseController
{
    /**
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     */
    public function displayAjaxGetWebhookNotifications(): void
    {
        $storeId = Tools::getValue('storeId');
        $page = Tools::getValue('page');
        $limit = Tools::getValue('limit');

        $result = AdminAPI::get()->webhookNotifications($storeId)->getNotifications($page, $limit);
        if (!$result->isSuccessful()) {
            AdyenPrestaShopUtility::dieJson($result);

            return;
        }

        $jsonResponse = $result->toArray();
        $references = $this->getMerchantReferences($jsonResponse['notifications']);
        $mapId = $this->mapOrderNumbers($references);
        $mapLink = $this->mapOrderLink($references);

        foreach ($jsonResponse['notifications'] as $key => $item) {
            $jsonResponse['notifications'][$key]['orderId'] = (string)$mapId[$item['orderId']];
            $jsonResponse['notifications'][$key]['details']['shopLink'] = (string)$mapLink[$item['orderId']];
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
            $orderNumbers[$reference] = Order::getIdByCartId((int)$reference);
        }

        return $orderNumbers;
    }

    /**
     * @param string[] $references
     *
     * @return array
     */
    private function mapOrderLink(array $references): array
    {
        if (empty($references)) {
            return [];
        }

        $orderLinks = [];
        foreach ($references as $reference) {
            $orderLinks[$reference] = $this->orderService()->getOrderUrl($reference);
        }

        return $orderLinks;
    }

    /**
     * @return OrderService
     */
    private function orderService(): OrderService
    {
        return ServiceRegister::getService(OrderService::class);
    }
}
