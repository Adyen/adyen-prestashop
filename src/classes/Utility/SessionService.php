<?php

namespace AdyenPayment\Classes\Utility;

/**
 * Class CookieService
 *
 * @package AdyenPayment\Classes\Utility
 */
class SessionService
{
    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public static function set(string $key, $value)
    {
        if (empty(session_id())) {
            session_start();
        }

        $_SESSION[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function get(string $key)
    {
        $result = '';

        if (empty(session_id())) {
            session_start();
        }

        if (isset($_SESSION[$key])) {
            $result = $_SESSION[$key];
            unset($_SESSION[$key]);
        }

        return $result;
    }
}
