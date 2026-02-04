<?php

class Autoloader
{
    /**
     * File extension as a string. Defaults to ".php".
     */
    protected static $fileExt = '.php';

    /**
     * The top level directory where recursion will begin. Defaults to the current
     * directory.
     */
    protected static $pathTop = __DIR__ . '/../';

    /**
     * A placeholder to hold the file iterator so that directory traversal is only
     * performed once.
     */
    protected static $fileIterator;

    /**
     * Autoload function for registration with spl_autoload_register
     *
     * Looks recursively through project directory and loads class files based on
     * filename match.
     *
     * @param string $className
     */
    public static function loader(string $className)
    {
        if (strpos($className, 'AdyenPayment') !== false) {
            $path = str_replace('AdyenPayment\\', '', $className);
            $parts = explode('\\', $path);
            $firstDir = strtolower($parts[0]);
            unset($parts[0]);

            include_once static::$pathTop . $firstDir . '/' . implode('/', $parts) . static::$fileExt;
        }

        if (strpos($className, 'Adyen\Core') !== false) {
            $path = str_replace('Adyen\Core\\', '', $className);

            include_once static::$pathTop . '/vendor/adyen/integration-core/src/' . str_replace('\\', '/', $path) . static::$fileExt;
        }

        if (strpos($className, 'Adyen\Webhook') !== false) {
            $path = str_replace('Adyen\Webhook\\', '', $className);

            include_once static::$pathTop . '/vendor/adyen/php-webhook-module/src/' . str_replace('\\', '/', $path) . static::$fileExt;
        }
    }

    /**
     * Sets the $fileExt property
     *
     * @param string $fileExt The file extension used for class files.  Default is "php".
     */
    public static function setFileExt($fileExt)
    {
        static::$fileExt = $fileExt;
    }

    /**
     * Sets the $path property
     *
     * @param string $path The path representing the top level where recursion should
     *                     begin. Defaults to the current directory.
     */
    public static function setPath($path)
    {
        static::$pathTop = $path;
    }
}
