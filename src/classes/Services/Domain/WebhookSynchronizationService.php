<?php

namespace AdyenPayment\Classes\Services\Domain;

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use Adyen\Core\BusinessLogic\Domain\Webhook\Services\WebhookSynchronizationService as CoreWebhookSynchronizationService;
use Cart;

/**
 * Class WebhookSynchronizationService
 *
 * @package AdyenPayment\Classes\Services\Domain
 */
class WebhookSynchronizationService extends CoreWebhookSynchronizationService
{
    /**
     * @param Webhook $webhook
     *
     * @return bool
     *
     * @throws InvalidMerchantReferenceException
     */
    public function isSynchronizationNeeded(Webhook $webhook): bool
    {
        $cart = new Cart((int)$webhook->getMerchantReference());

        return !$this->hasDuplicates(
                $this->transactionHistoryService->getTransactionHistory($webhook->getMerchantReference()),
                $webhook
            ) && (int)$cart->id_shop === (int)StoreContext::getInstance()->getStoreId();
    }
}
