<?php

namespace AdyenPayment\Classes\Entities;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Adyen\Core\Infrastructure\ORM\Configuration\EntityConfiguration;
use Adyen\Core\Infrastructure\ORM\Configuration\IndexMap;
use Adyen\Core\Infrastructure\ORM\Entity;

class LastOpenTime extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    public const CLASS_NAME = __CLASS__;
    /**
     * @var string
     */
    protected $timestamp;

    protected $fields = ['id', 'timestamp'];

    /**
     * {@inheritDoc}
     */
    public function getConfig(): EntityConfiguration
    {
        $indexMap = new IndexMap();

        return new EntityConfiguration($indexMap, 'LastOpenTime');
    }

    /**
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    /**
     * @param string $timestamp
     */
    public function setTimestamp(string $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
