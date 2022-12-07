<?php

namespace Adyen\PrestaShop\service;

class Modification extends \Adyen\Service\Modification
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
    }
}
