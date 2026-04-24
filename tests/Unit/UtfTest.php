<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UtfTest extends TestCase
{
    private \UTF $utf;

    protected function setUp(): void
    {
        $this->utf = new \UTF();
    }

    public function testStrlen(): void
    {
        $this->assertSame(5, $this->utf->strlen('héllo'));
        $this->assertSame(7, $this->utf->strlen('Привет!'));
    }

    public function testSubstr(): void
    {
        $this->assertSame('При', $this->utf->substr('Привет', 0, 3));
        $this->assertSame('вет', $this->utf->substr('Привет', 3));
    }

    public function testStrposReturnsCharOffset(): void
    {
        $this->assertSame(2, $this->utf->strpos('héllo', 'l'));
        $this->assertSame(0, $this->utf->strpos('Привет', 'П'));
        $this->assertSame(3, $this->utf->strpos('Привет', 'в'));
    }

    public function testStriposCaseInsensitive(): void
    {
        $this->assertSame(0, $this->utf->stripos('HéLLo', 'h'));
    }

    public function testStrrev(): void
    {
        $this->assertSame('тевирП', $this->utf->strrev('Привет'));
    }

    public function testTranslateDecodesUEscapes(): void
    {
        $this->assertSame('☺', $this->utf->translate('\u263a'));
    }

    public function testBomReturnsThreeBytes(): void
    {
        $this->assertSame("\xef\xbb\xbf", $this->utf->bom());
    }
}
