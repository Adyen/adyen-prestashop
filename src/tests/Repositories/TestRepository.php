<?php

namespace AdyenPayment\Tests\Repositories;

use AdyenPayment\Classes\Repositories\BaseRepository;

/**
 * Class TestRepository
 *
 * @package AdyenPayment\Tests\Repositories
 */
class TestRepository extends BaseRepository
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'adyen_test';
}
