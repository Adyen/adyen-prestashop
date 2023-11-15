<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Configuration;
use Exception;
use PrestaShopException;
use WebserviceKey;

/**
 * Class AuthorizationService
 *
 * @package AdyenPayment\E2ETest\Services
 */
class AuthorizationService
{
    /**
     *  Returns api key for PrestaShop Webservice API calls
     *
     * @return string
     * @throws PrestaShopException
     * @throws Exception
     */
    public function getAuthorizationCredentials(): string
    {
        Configuration::updateValue('PS_WEBSERVICE', 1);

        $apiAccess = new WebserviceKey();
        $apiAccess->key = $this->getRandomString();
        $apiAccess->save();
        $permissions = [
            'shops' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'shop_urls' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
        ];
        WebserviceKey::setPermissionForAccount($apiAccess->id, $permissions);

        return base64_encode($apiAccess->key . ':');
    }

    /**
     * @throws Exception
     */
    private function getRandomString(): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';

        for ($i = 0; $i < 32; $i++) {
            $index = random_int(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return $randomString;
    }
}