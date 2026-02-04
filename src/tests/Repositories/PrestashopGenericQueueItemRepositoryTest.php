<?php

namespace AdyenPayment\Tests\Repositories;

use Adyen\Core\Tests\Infrastructure\ORM\AbstractGenericQueueItemRepositoryTest;
use AdyenPayment\Classes\Bootstrap;

/**
 * Class PrestashopGenericQueueItemRepositoryTest
 */
class PrestashopGenericQueueItemRepositoryTest extends AbstractGenericQueueItemRepositoryTest
{
    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        $adyenTestTableUninstallScript = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'adyen_test';
        \Db::getInstance()->execute($adyenTestTableUninstallScript);
    }

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        Bootstrap::init();
        parent::setUp();
        $this->createTestTable();
    }

    /**
     * @return string
     */
    public function getQueueItemEntityRepositoryClass(): string
    {
        return TestQueueItemRepository::getClassName();
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage(): void
    {
        \Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'adyen_test');
    }

    /**
     * Creates a table for testing purposes.
     */
    private function createTestTable(): void
    {
        $adyenTestTableInstallScript =
            'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'adyen_test
            (
             `id` INT NOT NULL AUTO_INCREMENT,
             `type` VARCHAR(128) NOT NULL,
             `index_1` VARCHAR(255),
             `index_2` VARCHAR(255),
             `index_3` VARCHAR(255),
             `index_4` VARCHAR(255),
             `index_5` VARCHAR(255),
             `index_6` VARCHAR(255),
             `index_7` VARCHAR(255),
             `index_8` VARCHAR(255),
             `index_9` VARCHAR(255),
             `data` LONGTEXT NOT NULL,
              PRIMARY KEY(`id`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        \Db::getInstance()->execute($adyenTestTableInstallScript);
    }
}
