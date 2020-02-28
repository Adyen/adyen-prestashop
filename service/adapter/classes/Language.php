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

namespace Adyen\PrestaShop\service\adapter\classes;

use Adyen\PrestaShop\application\VersionChecker;

class Language
{
    /**
     * @var VersionChecker
     */
    private $versionChecker;

    /**
     * Language constructor.
     *
     * @param VersionChecker $versionChecker
     */
    public function __construct(VersionChecker $versionChecker)
    {
        $this->versionChecker = $versionChecker;
    }

    /**
     * Returns the locale code for 1.6 and 1.7
     *
     * @param \LanguageCore $language
     * @return string
     */
    public function getLocaleCode(\LanguageCore $language)
    {
        // no locale in PrestaShop1.6 only languageCode that is en-en but we need en_EN
        if ($this->versionChecker->isPrestaShop16()) {
            return $language->iso_code;
        } else {
            return $language->locale;
        }
    }
}
