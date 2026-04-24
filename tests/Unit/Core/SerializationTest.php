<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Hive serialization helpers: stringify, csv, serialize/unserialize.
 */
final class SerializationTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
    }

    public function testCsvProducesQuotedList(): void
    {
        $out = $this->f3->csv([1, 'two', 'three']);
        $this->assertSame("1,'two','three'", $out);
    }

    public function testSerializeUnserializeRoundTrip(): void
    {
        $data = ['a' => 1, 'b' => [2, 3], 'c' => 'x'];
        $s = $this->f3->serialize($data);
        $this->assertIsString($s);
        $this->assertSame($data, $this->f3->unserialize($s));
    }

    public function testStringifyAssoc(): void
    {
        $out = $this->f3->stringify(['a' => 1, 'b' => 'x']);
        $this->assertStringContainsString("'a'", $out);
        $this->assertStringContainsString("=>", $out);
    }

    public function testStringifyHandlesRecursion(): void
    {
        $a = ['x' => 1];
        $a['self'] = &$a;
        $out = $this->f3->stringify($a);
        $this->assertIsString($out);
        $this->assertStringContainsString('*RECURSION*', $out);
    }
}
