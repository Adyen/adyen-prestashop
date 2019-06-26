<?php

class Data
{
    /**
     * Get origin key for a specific origin using the adyen api library client
     *
     * @param $origin
     * @param int|null $storeId
     * @return string
     * @throws \Adyen\AdyenException
     */
    public function getOriginKeyForOrigin()
    {
        $origin = Tools::getShopDomainSSL(true, true);
        $params = [
            "originDomains" => [
                $origin
            ]
        ];

        $client = $this->initializeAdyenClient();
        try {
            $service = $this->createAdyenCheckoutUtilityService($client);
            $response = $service->originKeys($params);
        } catch (\Exception $e) {
        }

        $originKey = "";

        if (!empty($response['originKeys'][$origin])) {
            $originKey = $response['originKeys'][$origin];
        }

        return $originKey;
    }


    public function isDemoMode()
    {
        return Configuration::get('ADYEN_MODE');
    }

    public function adyenLogger()
    {
        $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
        $logger->setFilename(_PS_ROOT_DIR_ . '/var/logs/debug.log');
        return $logger;
    }

    /**
     * Initializes and returns Adyen Client and sets the required parameters of it
     *
     * @param int|null $storeId
     * @param string|null $apiKey
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    public function initializeAdyenClient()
    {
        $apiKey = $this->getAPIKey();
        $client = $this->createAdyenClient();
        $client->setApplicationName("Prestashop plugin");
        $client->setXApiKey($apiKey);

        if ($this->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            //todo liveendpointprefix
//            $client->setEnvironment(\Adyen\Environment::LIVE, $this->getLiveEndpointPrefix($storeId));
        }


        return $client;
    }

    /**
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    private function createAdyenClient()
    {
        return new \Adyen\Client();
    }

    /**
     * Retrieve the API key
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAPIKey()
    {
        if ($this->isDemoMode()) {
            $apiKey = $this->decrypt(Configuration::get('ADYEN_APIKEY_TEST'));
        } else {
            $apiKey = $this->decrypt(Configuration::get('ADYEN_APIKEY_LIVE'));
        }
        return $apiKey;
    }

    public function encrypt($data)
    {
        // Generate an initialization vector
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-ctr'));
        // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
        $encrypted = openssl_encrypt($data, 'aes-256-ctr', _COOKIE_KEY_, 0, $iv);
        // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
        return base64_encode($encrypted . '::' . $iv);
    }

    public function decrypt($data)
    {

        // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
        list($data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($data, 'aes-256-ctr', _COOKIE_KEY_, 0, $iv);
    }

    /**
     * @param \Adyen\Client $client
     * @return \Adyen\Service\CheckoutUtility
     * @throws \Adyen\AdyenException
     */
    private function createAdyenCheckoutUtilityService($client)
    {
        return new \Adyen\Service\CheckoutUtility($client);
    }
}