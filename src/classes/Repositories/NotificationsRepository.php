<?php

namespace AdyenPayment\Classes\Repositories;

use Adyen\Core\BusinessLogic\DataAccess\Notifications\Contracts\ShopNotificationRepository;

/**
 * Class NotificationsRepository
 *
 * @package AdyenPayment\Classes\Repositories
 */
class NotificationsRepository extends BaseRepositoryWithConditionalDelete implements ShopNotificationRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    public const TABLE_NAME = 'adyen_notifications';

    /**
     * Retrieves db_name for DBAL.
     *
     * @return string
     */
    protected function getDbName(): string
    {
        return 'adyen_notifications';
    }
}
