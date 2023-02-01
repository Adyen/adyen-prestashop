<?php

if (isset($argv, $argc) && $argc >= 2) {
    array_shift($argv);
    foreach ($argv as $dir) {
        addLicence($dir);
    }
} else {
    echo 'Usage: php autoLicence.php [directory...]';
}

function addLicence($dirPath)
{
    $excludedNames = [
        '.',
        '..',
        'index.php',
        'lib',
        'translations',
        'vendor',
        'css',
        'img',
        'tests',
        'prestashop-ui-kit.js',
    ];
    $dir = opendir($dirPath);

    while (false !== ($file = readdir($dir))) {
        if (!in_array($file, $excludedNames, true)) {
            if (is_dir($dirPath . '/' . $file)) {
                addLicence($dirPath . '/' . $file);
            } else {
                addLicenceToFile($dirPath . '/' . $file);
            }
        }
    }

    closedir($dir);
}

function addLicenceToFile($path)
{
    $licence = ' *                       ######
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
';

    $file = file_get_contents($path);
    if (strpos($file, '* Adyen PrestaShop plugin') !== false) {
        return;
    }

    switch (pathinfo($path, PATHINFO_EXTENSION)) {
        case 'php':
            // strip php header
            $file = substr($file, 5);
            $header = "<?php\n/**\n" . $licence . " */\n\n";
            break;
        case 'tpl':
            $header = "{**\n" . $licence . " *}\n\n";
            break;
        case 'js':
            $header = "/**\n" . $licence . " */\n\n";
            break;
        default:
            return;
    }

    file_put_contents($path, $header . trim($file) . "\n");
}
