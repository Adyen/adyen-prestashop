<?php

namespace AdyenPayment\Classes\Repositories;

use Adyen\Core\BusinessLogic\DataAccess\AdyenGiving\Contracts\AdyenGivingRepository as BaseAdyenGivingRepository;

/**
 * Class AdyenGivingRepository
 */
class AdyenGivingRepository extends BaseRepositoryWithConditionalDelete implements BaseAdyenGivingRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
}
