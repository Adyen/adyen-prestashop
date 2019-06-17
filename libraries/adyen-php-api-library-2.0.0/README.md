# Adyen APIs Library for PHP

[![Build Status](https://api.travis-ci.org/Adyen/adyen-php-api-library.svg?branch=master)](https://travis-ci.org/Adyen/adyen-php-api-library)

The Adyen API Library for PHP lets you easily work with Adyen's API.

## Integration
The Library supports all APIs under the following services:

* checkout
* checkout utility
* payments
* modifications
* payouts
* recurring
* marketpay
* Terminal API (Cloud based)

## Requirements
PHP 5.3 or higher

## Installation ##
You can use Composer or simply Download the Release

### Composer ###

The preferred method is via [composer](https://getcomposer.org). Follow the
[installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have
composer installed.


Once composer is installed, execute the following command in your project root to install this library:

```sh
composer require adyen/php-api-library
```

## Usage
To make the automatice testing cases working for your account change the credentials in the config/test.ini file.

### Examples ###

Create Payment Request on Test:
```php

$client = new \Adyen\Client();
$client->setApplicationName("Adyen PHP Api Library Example");
$client->setUsername("YOUR USERNAME");
$client->setPassword("YOUR PASSWORD");
$client->setXApiKey("YOUR API KEY");
$client->setEnvironment(\Adyen\Environment::TEST);

$service = new \Adyen\Service\Payment($client);

$json = '{
      "card": {
        "number": "4111111111111111",
        "expiryMonth": "10",
        "expiryYear": "2020",
        "cvc": "737",
        "holderName": "John Smith"
      },
      "amount": {
        "value": 1500,
        "currency": "EUR"
      },
      "reference": "payment-test",
      "merchantAccount": "YOUR MERCHANT ACCOUNT"
}';

$params = json_decode($json, true);

$result = $service->authorise($params);

```

For CSE use

```php
{
  "amount": {
    "value": 1499,
    "currency": "GBP"
  },
  "reference": "payment-test",
  "merchantAccount": "YOUR MERCHANT ACCOUNT",
  "additionalData": {
    "card.encrypted.json": "THE ENCRYPTED DATA"
  }
}
```

Refund example:

```php

$client = new \Adyen\Client();
$client->setApplicationName("Adyen PHP Api Library Example");
$client->setUsername("YOUR USERNAME");
$client->setPassword("YOUR PASSWORD");
$client->setXApiKey("YOUR API KEY");
$client->setEnvironment(\Adyen\Environment::TEST);

// intialize modification service
$service = new \Adyen\Service\Modification($client);

// set the amount you want to refund
$modificationAmount = array('currency' => 'CURRENCY', 'value' => 'VALUE');

// required are merchantAccount, ModificationAmount(currency,value), reference and originalReference
$params = array(
    "merchantAccount" => 'YOUR MERCHANT ACCOUNT',
    "modificationAmount" => $modificationAmount,
    "reference" => 'YOUR OWN REFERENCE',
    "originalReference" => 'PSPREFERENCE OF THE PAYMENT YOU WANT TO REFUND'
);

$result = $service->refund($params);

// $result['response'] = [refund-received]

```

## Documentation ##
* https://docs.adyen.com/developers/development-resources/libraries
* https://docs.adyen.com/developers/checkout/api-integration

## Tests ##
For the test cases you need the PCI permission enabled on you account. There are no test cases for CSE because credit card data is encrypted through our javascript library.
By default the test will then be skipped. If you have these permissions fill in your account details in the config/test.ini file to let the test work.

## Support
If you have any problems, questions or suggestions, create an issue here or send your inquiry to support@adyen.com.

## Licence
MIT license. For more information, see the LICENSE file.
