<?php

namespace AdyenPayment\Classes\Repositories;

use Adyen\Core\BusinessLogic\DataAccess\Payment\Contracts\PaymentsRepository;

/**
 * Class PaymentMethodRepository
 *
 * @package AdyenPayment\Classes\Repositories
 */
class PaymentMethodRepository extends BaseRepositoryWithConditionalDelete implements PaymentsRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
}
