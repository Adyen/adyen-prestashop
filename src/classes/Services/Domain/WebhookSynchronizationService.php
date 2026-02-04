<?php

namespace AdyenPayment\Classes\Services\Domain;

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\BusinessLogic\Domain\TransactionHistory\Exceptions\InvalidMerchantReferenceException;
use Adyen\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use Adyen\Core\BusinessLogic\Domain\Webhook\Services\WebhookSynchronizationService as CoreWebhookSynchronizationService;
use Adyen\Webhook\EventCodes;

/**
 * Class WebhookSynchronizationService
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
        $cart = new \Cart((int) $webhook->getMerchantReference());
        if ((int) $cart->id_shop !== (int) StoreContext::getInstance()->getStoreId()) {
            return false;
        }

        $transactionHistory = $this->transactionHistoryService->getTransactionHistory($webhook->getMerchantReference());
        if (
            $webhook->getEventCode() !== EventCodes::AUTHORISATION
            && $transactionHistory->collection()->filterAllByEventCode('PAYMENT_REQUESTED')->isEmpty()
        ) {
            return false;
        }

        return !$this->hasDuplicates(
            $transactionHistory,
            $webhook
        );
    }
}
