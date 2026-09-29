<?php

namespace AdyenPayment\Tests;

use AdyenPayment\Classes\Utility\WebSdk;
use PHPUnit\Framework\TestCase;

/**
 * Format checks for the Web SDK constants. Hash correctness against the CDN is verified by
 * dist/scripts/verify_web_sdk_sri.sh in CI.
 */
class WebSdkTest extends TestCase
{
    public function testIntegrityHashesAreSha384(): void
    {
        $this->assertMatchesSriFormat(WebSdk::JS_INTEGRITY);
        $this->assertMatchesSriFormat(WebSdk::CSS_INTEGRITY);
    }

    public function testJsAndCssUseDifferentHashes(): void
    {
        $this->assertNotSame(WebSdk::JS_INTEGRITY, WebSdk::CSS_INTEGRITY);
    }

    public function testVersionIsSemantic(): void
    {
        $this->assertSame(1, preg_match('/^\d+\.\d+\.\d+$/', WebSdk::VERSION));
    }

    public function testUrlsPointAtTheConfiguredVersion(): void
    {
        $this->assertSame(WebSdk::BASE_URL . WebSdk::VERSION . '/adyen.js', WebSdk::jsUrl());
        $this->assertSame(WebSdk::BASE_URL . WebSdk::VERSION . '/adyen.css', WebSdk::cssUrl());
    }

    public function testHeaderTemplateExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../views/templates/hook/web-sdk.tpl');
    }

    private function assertMatchesSriFormat(string $hash): void
    {
        $this->assertSame(1, preg_match('/^sha384-[A-Za-z0-9+\/]{64}$/', $hash), $hash);
    }
}
