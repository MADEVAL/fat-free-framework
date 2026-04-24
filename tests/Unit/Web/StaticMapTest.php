<?php

declare(strict_types=1);

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Tests\Support\MockWeb;
use Web\Google\StaticMap;

final class StaticMapTest extends TestCase
{
    protected function tearDown(): void
    {
        MockWeb::restore();
    }

    public function testDumpBuildsQueryAndReturnsBody(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue('PNGDATA', ['HTTP/1.1 200 OK', 'Content-Type: image/png']);

        $sm = new StaticMap();
        $sm->center('Kyiv')->zoom('10')->size('400x400');
        $body = $sm->dump();

        $this->assertSame('PNGDATA', $body);
        $url = $mock->calls[0]['url'];
        $this->assertStringContainsString('center=Kyiv', $url);
        $this->assertStringContainsString('zoom=10', $url);
        $this->assertStringContainsString('size=400x400', $url);
    }

    public function testDumpReturnsFalseOnEmptyBody(): void
    {
        MockWeb::install();
        $this->assertFalse((new StaticMap())->size('1x1')->dump());
    }
}
