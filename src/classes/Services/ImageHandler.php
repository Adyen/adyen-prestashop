<?php

namespace AdyenPayment\Classes\Services;

/**
 * Class ImageHandler
 *
 * @package AdyenPayment\Classes\Utility
 */
class ImageHandler
{
    private const ADYEN_PAYMENT_IMAGE_DIRECTORY = _PS_IMG_DIR_ . 'adyen/';

    /**
     * Saves image in directory. If saving is successful returns true.
     *
     * @param string $file
     * @param string $fileName
     * @param string $storeId
     *
     * @return bool
     */
    public static function saveImage(string $file, string $fileName, string $storeId): bool
    {
        if(!file_exists(self::ADYEN_PAYMENT_IMAGE_DIRECTORY)) {
            mkdir(self::ADYEN_PAYMENT_IMAGE_DIRECTORY);
        }

        if (!file_exists(self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId)) {
            mkdir(self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId);
        }

        return move_uploaded_file($file, self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId . '/' . $fileName . '.png');
    }

    /**
     * Returns image url.
     *
     * @param string $fileName
     * @param string $storeId
     *
     * @return string
     */
    public static function getImageUrl(string $fileName, string $storeId): string
    {
        $shop = new \Shop($storeId);

        return $shop->getBaseUrl() . '/img/adyen/' . $storeId . '/' . $fileName . '.png';
    }

    /**
     * Removes image from directory.
     *
     * @param string $fileName
     * @param string $storeId
     *
     * @return void
     */
    public static function removeImage(string $fileName, string $storeId): void
    {
        if (file_exists(self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId . '/' . $fileName . '.png')) {
            unlink(self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId . '/' . $fileName . '.png');
        }
    }

    /**
     * Removes directory for specific store if.
     *
     * @param string $storeId
     *
     * @return void
     */
    public static function removeDirectoryForStore(string $storeId): void
    {
        if (file_exists(self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId)) {
            rmdir(self::ADYEN_PAYMENT_IMAGE_DIRECTORY . $storeId);
        }
    }

    /**
     * Removes adyen image directory.
     *
     * @return void
     */
    public static function removeAdyenDirectory(): void
    {
        if (file_exists(self::ADYEN_PAYMENT_IMAGE_DIRECTORY)) {
            rmdir(self::ADYEN_PAYMENT_IMAGE_DIRECTORY);
        }
    }
}
