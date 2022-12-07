<?php

namespace Adyen\PrestaShop\model;

abstract class AbstractModel
{
    /**
     * @var \Db
     */
    protected $dbInstance;

    /**
     * AdyenPaymentAction constructor.
     */
    public function __construct()
    {
        $this->dbInstance = \Db::getInstance();
    }
}
