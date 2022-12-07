<?php

namespace Adyen\PrestaShop\service\adapter\classes;

use Adyen\PrestaShop\application\VersionChecker;

class Controller
{
    /**
     * @var \FrontController
     */
    private $controller;

    /**
     * @var VersionChecker
     */
    private $versionChecker;

    public function __construct(VersionChecker $versionChecker)
    {
        $this->versionChecker = $versionChecker;
    }

    /**
     * @param \FrontController $controller
     */
    public function setController(\FrontController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @param string $id
     * @param string $relativePath
     * @param array $params
     */
    public function registerJavascript($id, $relativePath, array $params = [])
    {
        if ($this->versionChecker->isPrestaShop16()) {
            /* @noinspection PhpDeprecationInspection */
            $this->controller->addJS($relativePath);
        } else {
            $this->controller->registerJavascript($id, $relativePath, $params);
        }
    }

    /**
     * @param string $id
     * @param string $relativePath
     * @param array $params
     */
    public function registerStylesheet($id, $relativePath, $params = [])
    {
        if ($this->versionChecker->isPrestaShop16()) {
            /* @noinspection PhpDeprecationInspection */
            $this->controller->addCSS($relativePath);
        } else {
            $this->controller->registerStylesheet($id, $relativePath, $params);
        }
    }
}
