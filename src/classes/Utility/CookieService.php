<?php

namespace AdyenPayment\Classes\Utility;

/**
 * Class CookieService
 *
 * @package AdyenPayment\Classes\Utility
 */
class CookieService
{
    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public static function set(string $key, string $value)
    {
        setcookie($key, $value, time() + 3600, "/");
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public static function get(string $key): string
    {
        $result = '';

        if (isset($_COOKIE[$key])) {
            $result = $_COOKIE[$key];
            unset($_COOKIE[$key]);
            setcookie($key, '', time() - 3600, '/');
        }

        return $result;
    }
}
