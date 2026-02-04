<?php

namespace AdyenPayment\Classes\E2ETest\Services;

/**
 * Class AuthorizationService
 */
class AuthorizationService
{
    /**
     *  Generate and returns api key for PrestaShop Webservice API calls
     *
     * @return string
     *
     * @throws \PrestaShopException
     * @throws \Exception
     */
    public function getAuthorizationCredentials(): string
    {
        \Configuration::updateValue('PS_WEBSERVICE', 1);

        $apiAccess = new \WebserviceKey();
        $apiAccess->key = $this->getRandomString();
        $apiAccess->save();
        $permissions = [
            'shops' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'shop_urls' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'countries' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'currencies' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'customers' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'addresses' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'carts' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'orders' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
            'products' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'DELETE' => 1, 'HEAD' => 1],
        ];
        \WebserviceKey::setPermissionForAccount($apiAccess->id, $permissions);

        return base64_encode($apiAccess->key . ':');
    }

    /**
     * Generates random string of 32 characters
     *
     * @throws \Exception
     */
    private function getRandomString(): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';

        for ($i = 0; $i < 32; ++$i) {
            $index = random_int(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return $randomString;
    }
}
