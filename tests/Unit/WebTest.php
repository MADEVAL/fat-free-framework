<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WebTest extends TestCase
{
    private \Web $web;

    protected function setUp(): void
    {
        $this->web = \Web::instance();
    }

    public function testSlugAsciiSafe(): void
    {
        $this->assertSame('hello-world', $this->web->slug('Hello World!'));
    }

    public function testSlugTransliteratesUnicode(): void
    {
        $out = $this->web->slug('Привет мир');
        $this->assertNotEmpty($out);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $out);
    }

    public function testMimeFromExtension(): void
    {
        $this->assertSame('image/png', $this->web->mime('foo.png'));
        $this->assertSame('text/css', $this->web->mime('a.css'));
        $this->assertSame('application/pdf', $this->web->mime('doc.pdf'));
    }

    public function testMimeUnknownReturnsOctetStream(): void
    {
        $this->assertSame('application/octet-stream', $this->web->mime('weird.zzz'));
    }
}
