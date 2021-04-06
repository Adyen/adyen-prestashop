<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\exception\GenericLoggedException;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShopBundle\Utils\ZipManager;


class AdminAdyenOfficialPrestashopLogFetcherController extends ModuleAdminController
{
    /** @var ZipManager $zipManager */
    private $zipManager;

    /** @var \Adyen\PrestaShop\service\adapter\classes\Configuration $configuration */
    private $configuration;

    /**
     * @var Adyen\PrestaShop\infra\Crypto
     */
    private $crypto;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws CoreException
     */
    public function __construct()
    {
        $this->zipManager = ServiceLocator::get('PrestaShopBundle\Utils\ZipManager');
        $this->configuration = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Configuration');
        $this->crypto = ServiceLocator::get('Adyen\PrestaShop\infra\Crypto');

        // Required in order to automatically call the renderView function
        $this->display = 'view';
        $this->bootstrap = true;
        $this->toolbar_title[] = 'Adyen Logs';
        parent::__construct();

        $this->createCurrentApplicationInfoFile();
        $this->zipAndDownload();
    }

    public function renderView()
    {
        $tpl = $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/log-fetcher.tpl'
        );

        return $tpl->fetch();
    }

    /**
     * Zip all files in the adyen log directory and download
     */
    private function zipAndDownload()
    {
        $dir = _PS_ROOT_DIR_ . '/var/logs/adyen';
        $zip_file = Configuration::get('PS_SHOP_NAME') . '_' . date('Y-m-d') . '_' . 'Adyen_Logs.zip';

        // Get real path for our folder
        $rootPath = realpath($dir);

        $this->zipManager->createArchive($zip_file, $rootPath);


        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($zip_file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zip_file));
        readfile($zip_file);
    }

    /**
     * Create a text file with the current application info
     */
    private function createCurrentApplicationInfoFile()
    {
        $adyenPaymentSource = sprintf(
            'Adyen module version: %s',
            $this->configuration->moduleVersion
        );

        $prestashopVersion = sprintf("\nPrestashop version: %s", _PS_VERSION_);

        if (!empty($this->configuration->integratorName)) {
            $prestashopVersion .= sprintf(' with integrator: %s', $this->configuration->integratorName);
        }

        $environment = sprintf(
            "\nEnvironment: %s, with live endpoint prefix: %s",
            $this->configuration->adyenMode,
            $this->configuration->liveEndpointPrefix
        );

        $content = $adyenPaymentSource . $prestashopVersion . $environment . $this->getConfigurationValues();

        $filePath = fopen(_PS_ROOT_DIR_ . '/var/logs/adyen' . "/applicationInfo","wb");
        fwrite($filePath,$content);
        fclose($filePath);
    }

    /**
     * Get all remaining config values
     *
     * @return string
     * @throws GenericLoggedException
     * @throws MissingDataException
     */
    private function getConfigurationValues()
    {
        $configValues = "\n\nConfiguration values: \n";
        $configs['autoCronJobRunner'] = sprintf("\nAuto cronjob runner: %s", Configuration::get('ADYEN_AUTO_CRON_JOB_RUNNER'));
        $configs['storedPaymentMethods'] = sprintf("\nStored payment methods: %s", Configuration::get('ADYEN_ENABLE_STORED_PAYMENT_METHODS'));
        $configs['displayCollapse'] = sprintf("\nPayment display collapse: %s", Configuration::get('ADYEN_PAYMENT_DISPLAY_COLLAPSE'));
        $configs['merchantAccount'] = sprintf("\nMerchant account: %s", Configuration::get('ADYEN_MERCHANT_ACCOUNT'));
        $configs['mode'] = sprintf("\nMode: %s", Configuration::get('ADYEN_MODE'));
        $configs['notificationUser'] = sprintf("\nNotification user: %s", Configuration::get('ADYEN_NOTI_USERNAME'));
        $configs['clientKeyTest'] = sprintf("\nClient key test: %s", Configuration::get('ADYEN_CLIENTKEY_TEST'));
        $configs['clientKeyLive'] = sprintf("\nClient key live: %s", Configuration::get('ADYEN_CLIENTKEY_LIVE'));
        $configs['appleName'] = sprintf("\nApple pay merchant name: %s", Configuration::get('ADYEN_APPLE_PAY_MERCHANT_NAME'));
        $configs['appleIdentifier'] = sprintf("\nApple pay merchant identifier: %s", Configuration::get('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER'));
        $configs['googleGatewayMerchant'] = sprintf("\nGoogle pay merchant gateway id: %s", Configuration::get('ADYEN_APPLE_PAY_MERCHANT_NAME'));
        $configs['googleIdentifier'] = sprintf("\nGoogle pay merchant identifier: %s", Configuration::get('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER'));

        $configs['notificationPass'] = sprintf(
            "\nNotification password last 4: %s",
            substr($this->crypto->decrypt(Configuration::get('ADYEN_NOTI_PASSWORD')), -4)
        );

        $configs['notificationHmac'] = sprintf(
            "\nNotification HMAC last 4: %s",
            substr($this->crypto->decrypt(Configuration::get('ADYEN_NOTI_HMAC')), -4)
        );

        $configs['apiKeyTest'] = sprintf(
            "\nApi key test last 4: %s",
            substr($this->crypto->decrypt(Configuration::get('ADYEN_APIKEY_TEST')), -4)
        );

        $configs['apiKeyLive'] = sprintf(
            "\nApi key live last 4: %s",
            substr($this->crypto->decrypt(Configuration::get('ADYEN_APIKEY_LIVE')), -4)
        );

        foreach ($configs as $config) {
            $configValues .= $config;
        }

        return $configValues;
    }
}
