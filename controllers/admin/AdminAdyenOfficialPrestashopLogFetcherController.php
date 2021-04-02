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


class AdminAdyenOfficialPrestashopLogFetcherController extends ModuleAdminController
{

    /**
     * AdminAdyenPrestashopCronController constructor.
     *
     * @throws CoreException
     */
    public function __construct()
    {
        // Set variables
        $this->display = 'view';

        $this->bootstrap = true;
        parent::__construct();
    }

    public function renderView()
    {
        $tpl = $this->context->smarty->createTemplate(_PS_MODULE_DIR_ . $this->module->name.'/views/templates/admin/log-fetcher.tpl');

        return $tpl->fetch();
    }
}
