<?php

namespace AdyenPayment\Classes\Services;

use PrestaShop\PrestaShop\Adapter\Entity\PhpEncryption;

/**
 * Class Encryptor
 */
class Encryptor
{
    /**
     * Encrypts authorization data.
     *
     * @param string $data
     *
     * @return string
     */
    public static function encryptData(string $data): string
    {
        $path = _PS_ROOT_DIR_ . '/app/config/parameters.php';
        $config = require $path;
        $newCookieKey = $config['parameters']['new_cookie_key'];

        return (new PhpEncryption($newCookieKey))->encrypt($data);
    }

    /**
     * Decrypts authorization data.
     *
     * @param string $data
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function decryptData(string $data): string
    {
        $path = _PS_ROOT_DIR_ . '/app/config/parameters.php';
        $config = require $path;
        $newCookieKey = $config['parameters']['new_cookie_key'];

        return (new PhpEncryption($newCookieKey))->decrypt($data);
    }
}
