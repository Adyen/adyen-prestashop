<?php

namespace AdyenPayment\Classes\Services\Domain;

use Adyen\Core\BusinessLogic\Domain\Stores\Models\Store;
use Adyen\Core\BusinessLogic\Domain\Stores\Services\StoreService as DomainStoreService;

/**
 * Class StoreService
 */
class StoreService extends DomainStoreService
{
    /**
     * @return Store|null
     */
    public function getCurrentStore(): ?Store
    {
        return $this->integrationStoreService->getStoreById((string) \Context::getContext()->shop->id);
    }
}
