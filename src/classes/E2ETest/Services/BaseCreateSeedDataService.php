<?php

namespace AdyenPayment\Classes\E2ETest\Services;

/**
 * Class BaseCreateSeedDataService
 *
 * @package AdyenPayment\Classes\E2ETest\Services
 */
class BaseCreateSeedDataService
{
    /**
     * Reads from xml file
     *
     * @param string $fileName
     * @return string
     */
    protected function readFomXMLFile(string $fileName): string
    {
        return file_get_contents(
            './modules/adyenofficial/classes/E2ETest/Data/' . $fileName . '.xml',
            FILE_USE_INCLUDE_PATH
        );
    }

    /**
     * Reads from json file
     *
     * @return array
     */
    protected function readFromJSONFile(): array
    {
        $jsonString = file_get_contents(
            './modules/adyenofficial/classes/E2ETest/Data/test_data.json',
            FILE_USE_INCLUDE_PATH
        );

        return json_decode($jsonString, true);
    }
}