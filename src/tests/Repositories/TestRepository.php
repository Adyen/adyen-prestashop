<?php

namespace AdyenPayment\Tests\Repositories;

use AdyenPayment\Classes\Repositories\BaseRepository;

/**
 * Class TestRepository
 */
class TestRepository extends BaseRepository
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
