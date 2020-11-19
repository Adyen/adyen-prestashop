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

namespace Adyen\PrestaShop\model;

/**
 * Class Hashing to manage hash and crypto of user (clients/merchants) passwords.
 */
class Hashing
{
    /** @var array should contain hashing methods */
    private $hashMethods = array();
    /**
     * Check if it's the first function of the array that was used for hashing.
     *
     * @param string $passwd The password you want to check
     * @param string $hash The hash you want to check
     * @param string $staticSalt A static salt
     *
     * @return bool Result of the verify function
     */
    public function isFirstHash($passwd, $hash, $staticSalt = _COOKIE_KEY_)
    {
        if (!count($this->hashMethods)) {
            $this->initHashMethods();
        }
        $closure = reset($this->hashMethods);
        return $closure['verify']($passwd, $hash, $staticSalt);
    }
    /**
     * Iterate on hash_methods array and return true if it matches.
     *
     * @param string $passwd The password you want to check
     * @param string $hash The hash you want to check
     * @param string $staticSalt A static salt
     *
     * @return bool `true` is returned if the function find a match else false
     */
    public function checkHash($passwd, $hash, $staticSalt = _COOKIE_KEY_)
    {
        if (!count($this->hashMethods)) {
            $this->initHashMethods();
        }
        foreach ($this->hashMethods as $closure) {
            if ($closure['verify']($passwd, $hash, $staticSalt)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Hash the `$plaintextPassword` string and return the result of the 1st hashing method
     * contained in PrestaShop\PrestaShop\Core\Crypto\Hashing::hash_methods.
     *
     * @param string $plaintextPassword The password you want to hash
     * @param string $staticSalt The static salt
     *
     * @return string
     */
    public function hash($plaintextPassword, $staticSalt = _COOKIE_KEY_)
    {
        if (!count($this->hashMethods)) {
            $this->initHashMethods();
        }
        $closure = reset($this->hashMethods);
        return $closure['hash']($plaintextPassword, $staticSalt, $closure['option']);
    }
    /**
     * Init $hash_methods.
     */
    private function initHashMethods()
    {
        $this->hashMethods = array(
            'bcrypt' => array(
                'option' => array(),
                'hash' => function ($password) {
                    return password_hash($password, PASSWORD_BCRYPT);
                },
                'verify' => function ($password, $hash) {
                    return password_verify($password, $hash);
                },
            ),
            'md5' => array(
                'option' => array(),
                'hash' => function ($password, $staticSalt) {
                    return md5($staticSalt . $password);
                },
                'verify' => function ($password, $hash, $staticSalt) {
                    return md5($staticSalt . $password) === $hash;
                },
            ),
        );
    }
}
