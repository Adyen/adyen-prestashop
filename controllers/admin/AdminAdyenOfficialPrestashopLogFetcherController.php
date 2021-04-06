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

use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShopBundle\Utils\ZipManager;


class AdminAdyenOfficialPrestashopLogFetcherController extends ModuleAdminController
{
    /** @var ZipManager $zipManager */
    private $zipManager;

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws CoreException
     */
    public function __construct()
    {
        $this->zipManager = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get('PrestaShopBundle\Utils\ZipManager');

        // Required in order to automatically call the renderView function
        $this->display = 'view';
        $this->bootstrap = true;
        $this->toolbar_title[] = 'Adyen Logs';
        parent::__construct();

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
     * Zip log files and download
     */
    public function zipAndDownload()
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
}
