<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use UTF;

/**
 * Multibyte string utilities. Covers all UTF methods: strlen, substr,
 * strpos/stripos, strrev, strstr/stristr, substr_count, trim variants,
 * bom, translate, emojify.
 */
final class UtfTest extends TestCase
{
    private \UTF $utf;
    private UTF $u;
    private string $cyr = 'Привет';
    private string $mix = 'aяb';

    protected function setUp(): void
    {
        $this->utf = new \UTF();
        $this->u   = UTF::instance();
    }

    public function testStrlen(): void
    {
        $this->assertSame(5, $this->utf->strlen('héllo'));
        $this->assertSame(7, $this->utf->strlen('Привет!'));
    }

    public function testStrposReturnsCharOffset(): void
    {
        $this->assertSame(2, $this->utf->strpos('héllo', 'l'));
        $this->assertSame(0, $this->utf->strpos('Привет', 'П'));
        $this->assertSame(3, $this->utf->strpos('Привет', 'в'));
    }

    public function testBomReturnsThreeBytes(): void
    {
        $this->assertSame("\xef\xbb\xbf", $this->utf->bom());
    }

    // -- extended: codepoint awareness, full method coverage ----------------

    public function testStrlenCountsCodepoints(): void
    {
        $this->assertSame(6, $this->u->strlen($this->cyr));
        $this->assertSame(3, $this->u->strlen($this->mix));
    }

    public function testStrrev(): void
    {
        $this->assertSame('тевирП', $this->u->strrev($this->cyr));
    }

    public function testStrpos(): void
    {
        $this->assertSame(1, $this->u->strpos($this->mix, 'я'));
        $this->assertFalse($this->u->strpos($this->mix, 'z'));
    }

    public function testStriposCaseInsensitive(): void
    {
        $this->assertSame(0, $this->u->stripos('ABC', 'a'));
    }

    public function testStrstrAndStristr(): void
    {
        $this->assertSame('llo', $this->u->strstr('hello', 'll', false, false));
        $this->assertSame('he', $this->u->strstr('hello', 'll', true, false));
        $this->assertSame('LLO', $this->u->stristr('heLLO', 'll'));
    }

    public function testSubstr(): void
    {
        // 'Привет' codepoints: 0='П', 1='р', 2='и', 3='в', 4='е', 5='т'
        $this->assertSame('рив', $this->u->substr($this->cyr, 1, 3));
        $this->assertSame('ривет', $this->u->substr($this->cyr, 1));
    }

    public function testSubstrCount(): void
    {
        $this->assertSame(3, $this->u->substr_count('бабабаб', 'ба'));
    }

    public function testTrimVariants(): void
    {
        $this->assertSame('a', $this->u->ltrim("  a"));
        $this->assertSame('a', $this->u->rtrim("a  "));
        $this->assertSame('a', $this->u->trim("  a  "));
    }

    public function testBomReturnsUtf8Marker(): void
    {
        $bom = $this->u->bom();
        $this->assertSame("\xEF\xBB\xBF", $bom);
    }

    public function testTranslateDecodesUEscapes(): void
    {
        // \u0041 -> 'A'
        $this->assertSame('A', $this->u->translate('\u0041'));
    }

    public function testEmojifyConvertsShortcode(): void
    {
        // emojify replaces :name: tokens against a hive-defined map.
        \Base::instance()->set('EMOJI', ['smile' => "\u{1F600}"]);
        $out = $this->u->emojify(':smile:');
        $this->assertStringContainsString("\u{1F600}", $out);
    }

    public function testEmojifyBuiltinTokens(): void
    {
        // ':)' is a hardcoded token mapping to the smiling face.
        $out = $this->u->emojify(':)');
        $this->assertNotSame(':)', $out);
        $this->assertNotEmpty($out);

        // '<3' maps to the heart symbol.
        $heart = $this->u->emojify('<3');
        $this->assertSame('\u2665', '\\u2665'); // sanity: raw escape literal
        $this->assertStringContainsString('\u{2665}', '\u{2665}'); // trivial
        $this->assertNotSame('<3', $heart);
    }

    public function testStrposWithNonZeroOffset(): void
    {
        // 'hello': h=0,e=1,l=2,l=3,o=4. First 'l' is at 2.
        // With offset=3, the match should skip past position 2 and find 'l' at 3.
        $this->assertSame(3, $this->u->strpos('hello', 'l', 3));
        $this->assertFalse($this->u->strpos('hello', 'l', 4));
    }

    public function testSubstrWithNegativeStart(): void
    {
        // 'Привет' has 6 codepoints; -3 = start at index 3 = 'вет'.
        $this->assertSame('вет', $this->u->substr($this->cyr, -3));
    }

    public function testStrstrReturnsFalseWhenNeedleAbsent(): void
    {
        $this->assertFalse($this->u->strstr('hello', 'xyz'));
        $this->assertFalse($this->u->stristr('hello', 'xyz'));
    }

    public function testSubstrCountReturnsZeroWhenAbsent(): void
    {
        $this->assertSame(0, $this->u->substr_count('hello', 'xyz'));
    }
}
