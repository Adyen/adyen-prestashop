<?php

namespace AdyenPayment\Classes\Utility;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Adyen Web SDK version and Subresource Integrity hashes.
 *
 * When bumping VERSION, take both hashes from the "Updating to this version" section of the
 * Adyen release notes: https://docs.adyen.com/online-payments/release-notes
 */
final class WebSdk
{
    public const VERSION = '5.65.0';
    public const BASE_URL = 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/';
    public const JS_INTEGRITY = 'sha384-6qEAHFy5klBO9QE1zhxuGAKiAe7fVK7SAW7KnLSaYxj2UedBLGHsnNi7NrNcKIQR';
    public const CSS_INTEGRITY = 'sha384-Dk62669n9Ic7V6K8X7MBAOEZ5IQ9Qq29nW/zPkfwg1ghqyZLiuSc5QYQJ6M72iNR';

    /**
     * Returns the URL of the Web SDK script.
     *
     * @return string
     */
    public static function jsUrl(): string
    {
        return self::BASE_URL . self::VERSION . '/adyen.js';
    }

    /**
     * Returns the URL of the Web SDK stylesheet.
     *
     * @return string
     */
    public static function cssUrl(): string
    {
        return self::BASE_URL . self::VERSION . '/adyen.css';
    }
}
