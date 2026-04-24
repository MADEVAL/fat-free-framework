<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class BaseTest extends TestCase
{
    private \Base $f3;

    protected function setUp(): void
    {
        $this->f3 = \Base::instance();
    }

    public function testInstanceIsSingleton(): void
    {
        $this->assertSame($this->f3, \Base::instance());
    }

    public function testHiveSetAndGet(): void
    {
        $this->f3->set('foo', 'bar');
        $this->assertSame('bar', $this->f3->get('foo'));
    }

    public function testHiveDottedKeys(): void
    {
        $this->f3->set('a.b.c', 42);
        $this->assertSame(42, $this->f3->get('a.b.c'));
        $this->assertIsArray($this->f3->get('a'));
    }

    public function testClearRemovesKey(): void
    {
        $this->f3->set('todrop', 'x');
        $this->f3->clear('todrop');
        $this->assertNull($this->f3->get('todrop'));
    }

    public function testHashIsDeterministic(): void
    {
        $a = $this->f3->hash('hello');
        $b = $this->f3->hash('hello');
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $this->f3->hash('world'));
    }

    public function testSerializeRoundtrip(): void
    {
        $payload = ['x' => 1, 'y' => [2, 3, 4]];
        $s = $this->f3->serialize($payload);
        $this->assertSame($payload, $this->f3->unserialize($s));
    }

    public function testFormatNumberIsLocaleSafe(): void
    {
        // {0,number} should at minimum return the value as a string,
        // not throw, and not depend on a removed locale function.
        $out = $this->f3->format('{0,number}', 1234.5);
        $this->assertIsString($out);
        $this->assertNotEmpty($out);
    }

    public function testSplitTrimsAndDelimits(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->f3->split('a, b ,c'));
    }
}
