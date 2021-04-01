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

namespace Adyen\PrestaShop\infra;

use Adyen\PrestaShop\service\Logger;
use Tools;

class AsyncNotificationTrigger
{
    const WAIT_TIME_MICROSECONDS = 16000;
    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function trigger($adminPath, $token)
    {
        $hostname = Tools::getHttpHost(true);
        $cronControllerPath = 'index.php?fc=module&controller=AdminAdyenOfficialPrestashopCron';
        $baseUri = __PS_BASE_URI__;
        $endpoint = "{$hostname}{$baseUri}{$adminPath}/{$cronControllerPath}&token={$token}";
        $endpointParts = parse_url($endpoint);

        if (!isset($endpointParts['path'])) {
            $endpointParts['path'] = '/';
        }

        if (!isset($endpointParts['port'])) {
            $endpointParts['port'] = $endpointParts['scheme'] === 'https' ? 443 : 80;
        }

        $request = "GET {$endpointParts['path']}?{$endpointParts['query']} HTTP/1.1\r\n";
        $request .= "Host: {$endpointParts['host']}\r\n\r\n";

        $prefix = Tools::substr($endpoint, 0, 8) === 'https://' ? 'tls://' : '';

        $socket = fsockopen(
            $prefix . $endpointParts['host'],
            $endpointParts['port'],
            $errorCode,
            $errorMessage
        );

        if (!$socket) {
            $this->logger->error(
                'Could not open a socket to notification endpoint',
                array(
                    'errorCode' => $errorCode,
                    'errorMessage' => $errorMessage,
                    'endpoint' => $endpoint
                )
            );
            return;
        }

        fwrite($socket, $request);
        usleep(self::WAIT_TIME_MICROSECONDS);
        fclose($socket);
    }
}
