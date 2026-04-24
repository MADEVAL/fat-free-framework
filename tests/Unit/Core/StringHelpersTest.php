<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Misc string/array helpers on Base: stringify, csv, camelcase, snakecase,
 * extract, constants, sign, split, fixslashes, trim.
 */
final class StringHelpersTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
    }

    public function testCamelcase(): void
    {
        $this->assertSame('helloWorld', $this->f3->camelcase('hello_world'));
        $this->assertSame('aBC', $this->f3->camelcase('a_b_c'));
        $this->assertSame('noChange', $this->f3->camelcase('noChange'));
    }

    public function testSnakecase(): void
    {
        $this->assertSame('hello_world', $this->f3->snakecase('helloWorld'));
        $this->assertSame('a_b_c', $this->f3->snakecase('aBC'));
    }

    public function testCamelcaseSnakecaseRoundtrip(): void
    {
        $this->assertSame('foo_bar_baz', $this->f3->snakecase($this->f3->camelcase('foo_bar_baz')));
    }

    public function testSign(): void
    {
        $this->assertSame(1, (int) $this->f3->sign(42));
        $this->assertSame(-1, (int) $this->f3->sign(-1));
        $this->assertSame(0, (int) $this->f3->sign(0));
    }

    public function testStringifyScalar(): void
    {
        $this->assertSame("'foo'", $this->f3->stringify('foo'));
        $this->assertSame('42', $this->f3->stringify(42));
        $this->assertSame('true', strtolower($this->f3->stringify(true)));
    }

    public function testStringifyNumericArrayProducesSquareBrackets(): void
    {
        $out = $this->f3->stringify([1, 2, 3]);
        $this->assertSame('[1,2,3]', $out);
    }

    public function testStringifyAssocArrayPreservesKeys(): void
    {
        $out = $this->f3->stringify(['a' => 1, 'b' => 2]);
        $this->assertStringContainsString("'a'=>1", $out);
        $this->assertStringContainsString("'b'=>2", $out);
    }

    public function testStringifyDetectsRecursion(): void
    {
        $a = ['x' => 1];
        $b = ['ref' => &$a];
        $a['back'] = &$b;
        $out = $this->f3->stringify($a);
        $this->assertStringContainsString('*RECURSION*', $out);
    }

    public function testCsv(): void
    {
        $this->assertSame("'a','b','c'", $this->f3->csv(['a', 'b', 'c']));
    }

    public function testExtractByPrefix(): void
    {
        $src = ['db_host' => 'h', 'db_port' => 5432, 'app_name' => 'x'];
        $out = $this->f3->extract($src, 'db_');
        $this->assertSame(['host' => 'h', 'port' => 5432], $out);
    }

    public function testConstantsReturnsClassConstantsByPrefix(): void
    {
        $consts = $this->f3->constants(\Base::class, 'HTTP_');
        $this->assertArrayHasKey('200', $consts);
        $this->assertSame('OK', $consts['200']);
        $this->assertArrayHasKey('404', $consts);
        $this->assertArrayNotHasKey('VERSION', $consts);
    }

    public function testParseSimpleKeyValueReturnsStrings(): void
    {
        // Base::parse() does NOT cast values; it preserves them as trimmed strings.
        $out = $this->f3->parse('a=1,b=2');
        $this->assertSame(['a' => '1', 'b' => '2'], $out);
    }

    public function testParseListValue(): void
    {
        $out = $this->f3->parse('items=[a,b,c]');
        $this->assertSame(['items' => ['a', 'b', 'c']], $out);
    }

    public function testCastInfersIntegerFloatBoolNull(): void
    {
        $this->assertSame(42, $this->f3->cast('42'));
        $this->assertSame(3.14, $this->f3->cast('3.14'));
        $this->assertTrue($this->f3->cast('true'));
        $this->assertFalse($this->f3->cast('false'));
        $this->assertNull($this->f3->cast('null'));
        $this->assertSame('hello', $this->f3->cast('hello'));
    }

    public function testFixslashesNormalisesDirectorySeparators(): void
    {
        $this->assertSame('a/b/c', $this->f3->fixslashes('a\\b\\c'));
    }

    public function testSplitOnPipesCommas(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->f3->split('a|b|c'));
        $this->assertSame(['a', 'b'], $this->f3->split('a, b'));
    }
}
