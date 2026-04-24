<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Magic;
use PHPUnit\Framework\TestCase;

/**
 * Magic abstract base behavior verified via a concrete in-memory subclass.
 * Covers ArrayAccess and __isset/__set/__get/__unset round-trips.
 */
final class MagicTest extends TestCase
{
    private Magic $bag;

    protected function setUp(): void
    {
        $this->bag = new MagicBag();
    }

    public function testSetGet(): void
    {
        $this->bag->set('foo', 'bar');
        $this->assertSame('bar', $this->bag->get('foo'));
    }

    public function testExistsAndClear(): void
    {
        $this->bag->set('k', 1);
        $this->assertTrue($this->bag->exists('k'));
        $this->bag->clear('k');
        $this->assertFalse($this->bag->exists('k'));
    }

    public function testArrayAccess(): void
    {
        $this->bag['a'] = 'A';
        $this->assertTrue(isset($this->bag['a']));
        $this->assertSame('A', $this->bag['a']);
        unset($this->bag['a']);
        $this->assertFalse(isset($this->bag['a']));
    }

    public function testMagicProperty(): void
    {
        $this->bag->prop = 'val';
        $this->assertTrue(isset($this->bag->prop));
        $this->assertSame('val', $this->bag->prop);
        unset($this->bag->prop);
        $this->assertFalse(isset($this->bag->prop));
    }
}

final class MagicBag extends Magic
{
    private array $store = [];

    public function exists($key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function &get($key)
    {
        return $this->store[$key];
    }

    public function set($key, $val)
    {
        return $this->store[$key] = $val;
    }

    public function clear($key): void
    {
        unset($this->store[$key]);
    }
}
