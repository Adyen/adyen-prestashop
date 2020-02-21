<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\infra;

use Adyen\PrestaShop\service\adapter\classes\Configuration;
use Adyen\PrestaShop\service\Logger;
use Tools;

class Crypto
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $method;

    public function __construct(Configuration $configuration, Logger $logger)
    {
        $this->method = 'aes-256-ctr';
        $this->configuration = $configuration;
        $this->logger = $logger;
    }

    public function encrypt($data)
    {
        // Generate an initialization vector
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
        // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
        return bin2hex($iv) . openssl_encrypt(
                $data,
                $this->method,
                $this->configuration->sslEncryptionKey,
                0,
                $iv
            );
    }

    /**
     * @param $data
     *
     * @return false|string
     */
    public function decrypt($data)
    {
        if (!$data) {
            $this->logger->debug('decrypt got empty parameter');
            return '';
        }

        $ivLength = openssl_cipher_iv_length($this->method);
        $iv = hex2bin(Tools::substr($data, 0, $ivLength * 2));
        return openssl_decrypt(
            Tools::substr($data, $ivLength * 2),
            $this->method,
            $this->configuration->sslEncryptionKey,
            0,
            $iv
        );
    }
}