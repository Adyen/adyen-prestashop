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
// phpcs:disable PSR1.Classes.ClassDeclaration

use Adyen\PrestaShop\service\Logger;
use Adyen\PrestaShop\service\adapter\classes\ServiceLocator;

class AdyenOfficialRedirectModuleFrontController
{
    /**
     * The list of approved POST parameters that this controller will post towards the Result adyen front controller
     *
     * @var string[]
     */
    private static $approvedPostParameters = array(
        'MD',
        'PaRes'
    );

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var LinkCore
     */
    private $link;

    /**
     * AdyenOfficialRedirectModuleFrontController constructor.
     */
    public function __construct()
    {

        // Init logger
        $this->logger = ServiceLocator::get('Adyen\PrestaShop\service\Logger');

        // Generates the Result Adyen module link where the request is going to be handled
        $this->link = new LinkCore();
        $redirectUrl = $this->link->getModuleLink(
            'adyenofficial',
            'Result',
            $_GET,
            true
        );

        // In case there is no POST request redirect back to the main page
        if (empty($_POST)) {
            header('Location: ' . $redirectUrl);
            exit;
        }

        // Prepare the parameters that can be forwarded to the Result Adyen module endpoint
        $params = $this->preparePostParameters($_POST);

        // Include template file which renders the form on the frontend using the params and redirectUrl variables
        include getcwd() . '/modules/adyenofficial/views/templates/front/redirect.php';

        // Exit here to avoid using the session cookie
        exit;
    }

    /**
     * @param $post
     * @return array
     */
    private function preparePostParameters($post)
    {
        $params = array();
        foreach ($post as $key => $value) {
            // In case the parameter is not approved, skip the item
            if (!in_array($key, self::$approvedPostParameters)) {
                $this->logger->warning('Unapproved post parameter was sent to the Redirect url: ' . json_encode($key));
                continue;
            }

            $escapedValue = htmlspecialchars($value, ENT_QUOTES);
            $params[$key] = $escapedValue;
        }

        return $params;
    }
}
