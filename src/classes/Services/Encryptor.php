<?php

namespace AdyenPayment\Classes\Services;

use PrestaShop\PrestaShop\Adapter\Entity\PhpEncryption;

/**
 * Class Encryptor
 *
 * @package AdyenPayment\Classes\Utility
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
        return (new PhpEncryption(_NEW_COOKIE_KEY_))->encrypt($data);
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
        return (new PhpEncryption(_NEW_COOKIE_KEY_))->decrypt($data);
    }
}
