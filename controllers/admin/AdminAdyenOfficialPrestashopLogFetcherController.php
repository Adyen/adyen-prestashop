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
 * @copyright (c) 2021 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

// This class is not in a namespace because of the way PrestaShop loads
// Controllers, which breaks a PSR1 element.
// phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\application\VersionChecker;
use Adyen\PrestaShop\exception\GenericLoggedException;
use Adyen\PrestaShop\exception\MissingDataException;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShopBundle\Utils\ZipManager;

require_once _PS_ROOT_DIR_ . '/modules/adyenofficial/vendor/autoload.php';

class AdminAdyenOfficialPrestashopLogFetcherController extends ModuleAdminController
{
    /** @var \Adyen\PrestaShop\service\adapter\classes\Configuration $configuration */
    private $configuration;

    /** @var Adyen\PrestaShop\infra\Crypto */
    private $crypto;

    /** @var VersionChecker */
    private $versionChecker;

    /** @var string $logsDirectory */
    private $logsDirectory;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws CoreException|PrestaShopException
     */
    public function __construct()
    {
        $this->configuration = ServiceLocator::get('Adyen\PrestaShop\service\adapter\classes\Configuration');
        $this->crypto = ServiceLocator::get('Adyen\PrestaShop\infra\Crypto');
        $this->versionChecker = ServiceLocator::get('Adyen\PrestaShop\application\VersionChecker');
        if ($this->versionChecker->isPrestaShop16()) {
            $this->logsDirectory = _PS_ROOT_DIR_ . '/log';
        } else {
            $this->logsDirectory = _PS_ROOT_DIR_ . '/var/logs';
        }

        // Required to automatically call the renderView function
        $this->display = 'view';
        $this->bootstrap = true;
        $this->toolbar_title[] = 'Logs';
        parent::__construct();
    }

    /**
     * Trigger zip download if param exists and exit
     *
     * @return bool|ObjectModel
     */
    public function postProcess()
    {
        if ((string)Tools::getValue('download')) {
            $this->createCurrentApplicationInfoFile();
            $this->zipAndDownload(Tools::getValue('include-all'));
            exit;
        }

        return parent::postProcess();
    }

    /**
     * Render the log-fetcher template
     *
     * @return false|string
     * @throws SmartyException
     */
    public function renderView()
    {
        $smartyVariables = array(
            'logo' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->module->name . '/views/img/adyen.png'),
            'downloadUrl' => $this->getDownloadUrl()
        );
        $this->addCSS('modules/' . $this->module->name . '/views/css/adyen_admin.css');

        // Passing variables in this call (instead of assign()) required for 1.6
        $tpl = $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/log-fetcher.tpl',
            null,
            null,
            $smartyVariables
        );

        return $tpl->fetch();
    }

    /**
     * Zip all log files. If includeAll is false, zip only the adyen log files
     *
     * @param $includeAll
     */
    private function zipAndDownload($includeAll)
    {
        if (!$includeAll) {
            $this->logsDirectory = $this->logsDirectory . '/adyen';
        }

        $zip_file = Configuration::get('PS_SHOP_NAME') . '_' . date('Y-m-d') . '_' . 'Adyen_Logs.zip';
        // Get real path for our folder
        $rootPath = realpath($this->logsDirectory);
        $this->createArchive($zip_file, $rootPath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . basename($zip_file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Content-Length: ' . filesize($zip_file));
        readfile($zip_file);
    }

    /**
     * Create the archive file. Function is a copy of ZipManager::createArchive, used in 1.7
     * TODO: Remove this and use ZipManager::createArchive when 1.6 support is dropped
     *
     * @param $filename
     * @param $folder
     */
    private function createArchive($filename, $folder)
    {
        $zip = new ZipArchive();

        $zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $filename => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = Tools::substr($filename, Tools::strlen($folder) + 1);

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
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

        $time = sprintf(
            "\nDate and time: %s",
            date('Y-m-d H:i:s')
        );

        $content = $adyenPaymentSource . $prestashopVersion . $environment . $time . $this->getConfigurationValues();

        $filePath = fopen($this->logsDirectory . "/applicationInfo", "wb");
        fwrite($filePath, $content);
        fclose($filePath);
    }

    /**
     * Get all remaining adyen config values
     *
     * @return string
     * @throws GenericLoggedException
     * @throws MissingDataException
     */
    private function getConfigurationValues()
    {
        $configs = array();
        $configValues = "\n\nConfiguration values: \n";
        $configs['autoCronJobRunner'] = sprintf(
            "\nAuto cronjob runner: %s",
            Configuration::get('ADYEN_AUTO_CRON_JOB_RUNNER')
        );
        $configs['storedPaymentMethods'] = sprintf(
            "\nStored payment methods: %s",
            Configuration::get('ADYEN_ENABLE_STORED_PAYMENT_METHODS')
        );
        $configs['displayCollapse'] = sprintf(
            "\nPayment display collapse: %s",
            Configuration::get('ADYEN_PAYMENT_DISPLAY_COLLAPSE')
        );
        $configs['merchantAccount'] = sprintf(
            "\nMerchant account: %s",
            Configuration::get('ADYEN_MERCHANT_ACCOUNT')
        );
        $configs['mode'] = sprintf("\nMode: %s", Configuration::get('ADYEN_MODE'));
        $configs['notificationUser'] = sprintf(
            "\nNotification user: %s",
            Configuration::get('ADYEN_NOTI_USERNAME')
        );
        $configs['clientKeyTest'] = sprintf(
            "\nClient key test: %s",
            Configuration::get('ADYEN_CLIENTKEY_TEST')
        );
        $configs['clientKeyLive'] = sprintf(
            "\nClient key live: %s",
            Configuration::get('ADYEN_CLIENTKEY_LIVE')
        );
        $configs['appleName'] = sprintf(
            "\nApple pay merchant name: %s",
            Configuration::get('ADYEN_APPLE_PAY_MERCHANT_NAME')
        );
        $configs['appleIdentifier'] = sprintf(
            "\nApple pay merchant identifier: %s",
            Configuration::get('ADYEN_APPLE_PAY_MERCHANT_IDENTIFIER')
        );
        $configs['googleGatewayMerchant'] = sprintf(
            "\nGoogle pay merchant gateway id: %s",
            Configuration::get('ADYEN_APPLE_PAY_MERCHANT_NAME')
        );
        $configs['googleIdentifier'] = sprintf(
            "\nGoogle pay merchant identifier: %s",
            Configuration::get('ADYEN_GOOGLE_PAY_MERCHANT_IDENTIFIER')
        );

        $apiKeyTest = Configuration::get('ADYEN_APIKEY_TEST');
        if (!empty($apiKeyTest)) {
            $configs['apiKeyTest'] = sprintf(
                "\nApi key test last 4: %s",
                Tools::substr($this->crypto->decrypt($apiKeyTest), -4)
            );
        }

        $apiKeyLive = Configuration::get('ADYEN_APIKEY_LIVE');
        if (!empty($apiKeyLive)) {
            $configs['apiKeyLive'] = sprintf(
                "\nApi key live last 4: %s",
                Tools::substr($this->crypto->decrypt($apiKeyLive), -4)
            );
        }

        foreach ($configs as $config) {
            $configValues .= $config;
        }

        return $configValues;
    }

    /**
     * Get the url accessed when the button is clicked, to download the zip file
     *
     * @return string
     */
    private function getDownloadUrl()
    {
        $adminDir = Tools::substr(PS_ADMIN_DIR, strrpos(PS_ADMIN_DIR, '/') + 1);
        $adminUrl = Tools::getAdminUrl(
            $adminDir . '/index.php?controller=AdminAdyenOfficialPrestashopLogFetcher&token='
        );
        $token = Tools::getAdminTokenLite('AdminAdyenOfficialPrestashopLogFetcher');

        return $adminUrl . $token;
    }
}
