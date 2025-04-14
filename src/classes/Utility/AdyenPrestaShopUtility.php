<?php

namespace AdyenPayment\Classes\Utility;

use Adyen\Core\BusinessLogic\AdminAPI\Response\Response;

/**
 * Class AdyenPrestaShopUtility
 *
 * @package AdyenPayment\Utility
 */
class AdyenPrestaShopUtility
{
    /**
     * Sets response header content type to json, echos supplied $data as json and terminates the process.
     *
     * @param Response|null $response
     */
    public static function dieJson(Response $response = null): void
    {
        header('Content-Type: application/json');

        if ($response && !$response->isSuccessful()) {
            static::setHttpStatusHeader($response);
        }

        die(json_encode($response ? $response->toArray() : []));
    }

    /**
     * Sets response header content type to json, echos supplied $data as json and terminates the process.
     *
     * @param array $response
     */
    public static function dieJsonArray(array $response ): void
    {
        header('Content-Type: application/json');

        die(json_encode($response));
    }

    /**
     * Die with 404 status in header.
     *
     * @param array $data
     *
     * @return void
     */
    public static function die404(array $data = []): void
    {
        header('HTTP/1.1 404 Not Found');

        die(json_encode($data));
    }

    /**
     * Die with 400 status in header.
     *
     * @param array $data
     *
     * @return void
     */
    public static function die400(array $data = []): void
    {
        header('HTTP/1.1 400 Bad Request');

        die(json_encode($data));
    }

    /**
     * Sets file specified by $filePath as response.
     *
     * @param string $filePath
     * @param string $outputFileName
     *
     * @return void
     */
    public static function dieFile(string $filePath, string $outputFileName = ''): void
    {
        $fileName = $outputFileName !== '' ? $outputFileName : basename($filePath);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);

        die(200);
    }

    /**
     * Sets response header content plaintext, echos $plainText and terminates the process.
     *
     * @param $plainText
     *
     * @return void
     */
    public static function diePlain($plainText = ''): void
    {
        header('Content-Type: text/plain');

        die($plainText);
    }

    /**
     * @param Response $response
     *
     * @return void
     */
    protected static function setHttpStatusHeader(Response $response): void
    {
        switch ($response->getStatusCode()) {
            case 200:
                header('HTTP/1.1 200 OK');
                break;
            case 400:
                header('HTTP/1.1 400 Bad request');
                break;
            case 401:
                header("HTTP/1.1 401 Unauthorized");
                break;
            case 404:
                header('HTTP/1.1 404 Bad request');
            case 503:
                header('HTTP/1.1 503 Service unavailable');
        }
    }
}
