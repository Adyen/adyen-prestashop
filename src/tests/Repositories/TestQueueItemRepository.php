<?php

namespace AdyenPayment\Tests\Repositories;

use AdyenPayment\Classes\Repositories\QueueItemRepository;

/**
 * Class TestQueueItemRepository
 */
class TestQueueItemRepository extends QueueItemRepository
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    public const TABLE_NAME = 'adyen_test';
}
