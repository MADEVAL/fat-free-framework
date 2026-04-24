<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the PHP 8.5 fixes.
 */
final class Php85CompatibilityTest extends TestCase
{
    /**
     * In PHP 8.5 setlocale($cat, 0) throws TypeError.
     * Preview::c() must still work without raising it.
     */
    public function testPreviewCDoesNotThrowOnPhp85(): void
    {
        $preview = new \Preview();
        $out = $preview->c(1.5);
        $this->assertSame('1.5', $out);
        $this->assertSame('0', $preview->c(0));
        $this->assertSame('-3.14', $preview->c(-3.14));
    }

    public function testPingbackClassIsRemoved(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 3) . '/web/pingback.php',
            'web/pingback.php must be removed (xmlrpc_* not in PHP 8.5)'
        );
        $this->assertFalse(
            class_exists(\Web\Pingback::class, true),
            '\Web\Pingback must not be autoloadable any more'
        );
    }

    public function testLegacyCacheBackendsAreNotReferencedInBase(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/base.php');
        $this->assertIsString($src);
        $this->assertStringNotContainsString('xcache_get(', $src);
        $this->assertStringNotContainsString('wincache_ucache_get(', $src);
        $this->assertStringNotContainsString('memcache_get(', $src);
        $this->assertStringNotContainsString('memcache_set(', $src);
        $this->assertStringNotContainsString('memcache_delete(', $src);
        $this->assertStringNotContainsString('memcache_connect(', $src);
    }

    public function testWebStillWorksWithCurlProtocolHandling(): void
    {
        // Just make sure the code path compiles and the constant logic is valid.
        // We don't actually issue a network call here.
        $this->assertTrue(class_exists(\Web::class));
        $src = file_get_contents(dirname(__DIR__, 3) . '/web.php');
        $this->assertStringContainsString('CURLOPT_PROTOCOLS_STR', $src);
    }
}
