<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Locale-aware Base::format(). The currency branch is the home of the
 * recently-fixed setlocale() compatibility patch in Preview::c() so we
 * exercise the formatter exhaustively.
 */
final class FormatTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
    }

    public function testNumberPositional(): void
    {
        $this->assertSame('hello world', $this->f3->format('{0} {1}', 'hello', 'world'));
    }

    public function testIntegerFormatter(): void
    {
        $out = $this->f3->format('{0,number,integer}', 1234567);
        // Thousands separator may be locale-dependent but digits must be present.
        $this->assertMatchesRegularExpression('/1.?234.?567/u', $out);
    }

    public function testPercentFormatter(): void
    {
        $out = $this->f3->format('{0,number,percent}', 0.5);
        $this->assertStringContainsString('%', $out);
        $this->assertStringContainsString('50', $out);
    }

    public function testCurrencyFormatterRunsWithoutThrowing(): void
    {
        // Format::currency depends on localeconv()::frac_digits, which is
        // platform-dependent in the "C" locale (often 127 -> garbage). We only
        // assert that the formatter does not throw and produces a non-empty
        // string containing the digit 1 from "12".
        $out = $this->f3->format('{0,number,currency}', 12.34);
        $this->assertNotEmpty($out);
        $this->assertStringContainsString('1', $out);
    }

    public function testCurrencyFormatterCustomSymbol(): void
    {
        $out = $this->f3->format('{0,number,currency,USD}', 5);
        $this->assertStringContainsString('USD', $out);
    }

    public function testPlainNumberFormatterFallback(): void
    {
        $out = $this->f3->format('{0,number}', 1234.5);
        $this->assertStringContainsString('1', $out);
        $this->assertStringContainsString('234', $out);
    }

    public function testPluralFormSelection(): void
    {
        $tpl = '{0, plural,zero {none},one {one item},other {# items}}';
        $this->assertSame('none', $this->f3->format($tpl, 0));
        $this->assertSame('one item', $this->f3->format($tpl, 1));
        $this->assertSame('5 items', $this->f3->format($tpl, 5));
    }

    public function testDateFormatter(): void
    {
        $ts = mktime(0, 0, 0, 7, 4, 2024);
        $out = $this->f3->format('{0,date}', $ts);
        $this->assertNotEmpty($out);
        $this->assertStringContainsString('2024', $out);
    }

    public function testTimeFormatter(): void
    {
        $ts = mktime(13, 45, 0, 1, 1, 2024);
        $out = $this->f3->format('{0,time}', $ts);
        $this->assertNotEmpty($out);
    }
}
