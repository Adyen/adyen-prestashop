<?php

namespace AdyenPayment\Classes\Repositories;

use Adyen\Core\BusinessLogic\DataAccess\TransactionLog\Contracts\ShopLogsRepository;

/**
 * Class TransactionLogRepository
 */
class TransactionLogRepository extends BaseRepositoryWithConditionalDelete implements ShopLogsRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    public const TABLE_NAME = 'adyen_transaction_log';

    /**
     * Retrieves db_name for DBAL.
     *
     * @return string
     */
    protected function getDbName(): string
    {
        return 'adyen_transaction_log';
    }
}
