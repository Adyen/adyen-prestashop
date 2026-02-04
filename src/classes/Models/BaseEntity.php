<?php

namespace AdyenPayment\Classes\Models;

use Adyen\Core\Infrastructure\ORM\Configuration\EntityConfiguration;
use Adyen\Core\Infrastructure\ORM\Configuration\IndexMap;
use Adyen\Core\Infrastructure\ORM\Entity;

/**
 * Class BaseEntity
 */
class BaseEntity extends Entity
{
    /**
     * @var string
     */
    protected $type;
    /**
     * @var string
     */
    protected $index_1;
    /**
     * @var string
     */
    protected $index_2;
    /**
     * @var string
     */
    protected $index_3;
    /**
     * @var string
     */
    protected $index_4;
    /**
     * @var string
     */
    protected $index_5;
    /**
     * @var string
     */
    protected $index_6;
    /**
     * @var string
     */
    protected $index_7;
    /**
     * @var string
     */
    protected $index_8;
    /**
     * @var string
     */
    protected $index_9;
    /**
     * @var string
     */
    protected $data;

    /**
     * @return string
     */
    public function getIndex1(): string
    {
        return $this->index_1;
    }

    /**
     * @param string $index_1
     */
    public function setIndex1(string $index_1): void
    {
        $this->index_1 = $index_1;
    }

    /**
     * @return string
     */
    public function getIndex2(): string
    {
        return $this->index_2;
    }

    /**
     * @param string $index_2
     */
    public function setIndex2(string $index_2): void
    {
        $this->index_2 = $index_2;
    }

    /**
     * @return string
     */
    public function getIndex3(): string
    {
        return $this->index_3;
    }

    /**
     * @param string $index_3
     */
    public function setIndex3(string $index_3): void
    {
        $this->index_3 = $index_3;
    }

    /**
     * @return string
     */
    public function getIndex4(): string
    {
        return $this->index_4;
    }

    /**
     * @param string $index_4
     */
    public function setIndex4(string $index_4): void
    {
        $this->index_4 = $index_4;
    }

    /**
     * @return string
     */
    public function getIndex5(): string
    {
        return $this->index_5;
    }

    /**
     * @param string $index_5
     */
    public function setIndex5(string $index_5): void
    {
        $this->index_5 = $index_5;
    }

    /**
     * @return string
     */
    public function getIndex6(): string
    {
        return $this->index_6;
    }

    /**
     * @param string $index_6
     */
    public function setIndex6(string $index_6): void
    {
        $this->index_6 = $index_6;
    }

    /**
     * @return string
     */
    public function getIndex7(): string
    {
        return $this->index_7;
    }

    /**
     * @param string $index_7
     */
    public function setIndex7(string $index_7): void
    {
        $this->index_7 = $index_7;
    }

    /**
     * @return string
     */
    public function getIndex8(): string
    {
        return $this->index_8;
    }

    /**
     * @param string $index_8
     */
    public function setIndex8(string $index_8): void
    {
        $this->index_8 = $index_8;
    }

    /**
     * @return string
     */
    public function getIndex9(): string
    {
        return $this->index_9;
    }

    /**
     * @param string $index_9
     */
    public function setIndex9(string $index_9): void
    {
        $this->index_9 = $index_9;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration
     */
    public function getConfig(): EntityConfiguration
    {
        $map = new IndexMap();
        $map->addStringIndex('type')
            ->addStringIndex('index_1')
            ->addStringIndex('index_2')
            ->addStringIndex('index_3')
            ->addStringIndex('index_4')
            ->addStringIndex('index_5')
            ->addStringIndex('index_6')
            ->addStringIndex('index_7')
            ->addStringIndex('index_8')
            ->addStringIndex('index_9')
            ->addStringIndex('data');

        return new EntityConfiguration($map, 'BaseMapping');
    }
}
