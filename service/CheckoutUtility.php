<?php

namespace Adyen\PrestaShop\service;

class CheckoutUtility extends \Adyen\Service\CheckoutUtility
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
    }
}
