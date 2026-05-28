<?php

namespace AdyenPayment\Classes\Repositories;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Adyen\Core\BusinessLogic\DataAccess\Payment\Contracts\PaymentsRepository;

/**
 * Class PaymentMethodRepository
 */
class PaymentMethodRepository extends BaseRepositoryWithConditionalDelete implements PaymentsRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
}
