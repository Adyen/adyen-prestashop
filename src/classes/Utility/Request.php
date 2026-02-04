<?php

namespace AdyenPayment\Classes\Utility;

/**
 * Class Request
 */
class Request
{
    /**
     * Retrieves post data.
     *
     * @return array
     */
    public static function getPostData(): array
    {
        $result = json_decode(file_get_contents('php://input'), true);

        return !empty($result) && is_array($result) ? $result : [];
    }
}
