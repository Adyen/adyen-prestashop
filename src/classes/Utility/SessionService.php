<?php

namespace AdyenPayment\Classes\Utility;

/**
 * Class CookieService
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
     * @param bool $delete
     *
     * @return mixed
     */
    public static function get(string $key, bool $delete = true)
    {
        $result = '';

        if (empty(session_id())) {
            session_start();
        }

        if (isset($_SESSION[$key])) {
            $result = $_SESSION[$key];

            if ($delete) {
                unset($_SESSION[$key]);
            }
        }

        return $result;
    }
}
