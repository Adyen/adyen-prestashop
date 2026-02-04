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
        'translations',
        'vendor',
        'css',
        'img',
        'tests',
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
    $licence = ' * ' . date('Y') . ' Adyen
 *
 * LICENSE PLACEHOLDER
 *
 * This source file is subject to the Apache License 2.0
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.apache.org/licenses/LICENSE-2.0.txt
 *
 * @author Adyen <support@adyen.com>
 * @copyright 2022 Adyen
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt  Apache License 2.0
';

    $file = file_get_contents($path);
    if (strpos($file, '* NOTICE OF LICENSE') !== false) {
        return;
    }

    switch (pathinfo($path, PATHINFO_EXTENSION)) {
        case 'php':
            // strip php header
            $file = substr($file, 5);
            $header = "<?php\n/**\n" . $licence . " */\n";
            break;
        case 'tpl':
            $header = "{**\n" . $licence . " *}\n";
            break;
        case 'js':
            $header = "/**\n" . $licence . " */\n";
            break;
        default:
            return;
    }

    file_put_contents($path, $header . trim($file) . "\n");
}
