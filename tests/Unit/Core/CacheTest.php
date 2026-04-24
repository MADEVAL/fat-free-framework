<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Cache;
use PHPUnit\Framework\TestCase;

/**
 * Cache class: folder backend round-trip, reset, auto-detect.
 */
final class CacheTest extends TestCase
{
    private string $dir;
    private \Cache $cache;

    protected function setUp(): void
    {
        $this->dir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cache-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->dir, 0777, true);
        $this->cache = new \Cache('folder=' . $this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        \Registry::clear(Cache::class);
    }

    public function testSetGetRoundtrip(): void
    {
        $this->assertTrue($this->cache->set('k1', 'value-1', 60) !== false);
        $this->assertSame('value-1', $this->cache->get('k1'));
    }

    public function testExistsReturnsArrayWithTimeAndTtl(): void
    {
        $this->cache->set('k2', ['a' => 1], 10);
        $info = $this->cache->exists('k2', $val);
        $this->assertIsArray($info);
        $this->assertCount(2, $info);
        $this->assertSame(['a' => 1], $val);
    }

    public function testClearRemovesEntry(): void
    {
        $this->cache->set('k3', 'gone', 60);
        $this->cache->clear('k3');
        $this->assertFalse($this->cache->get('k3'));
    }

    public function testResetWipesBackend(): void
    {
        $this->cache->set('k4', 'a', 60);
        $this->cache->set('k5', 'b', 60);
        $this->cache->reset();
        $this->assertFalse($this->cache->get('k4'));
        $this->assertFalse($this->cache->get('k5'));
    }

    // -- backend-agnostic variants and auto-detect ---------------------------

    public function testFolderBackendRoundTrip(): void
    {
        $c = new Cache('folder=' . $this->dir);
        // Cache::set returns the cached value (or its size) on success, FALSE on failure.
        $this->assertNotFalse($c->set('k', 'v', 5));
        $this->assertNotFalse($c->exists('k'));
        $this->assertSame('v', $c->get('k'));
        $this->assertTrue($c->clear('k'));
        $this->assertFalse($c->exists('k'));
    }

    public function testFolderBackendReset(): void
    {
        $c = new Cache('folder=' . $this->dir);
        $c->set('a', 1, 5);
        $c->set('b', 2, 5);
        $this->assertTrue($c->reset());
        $this->assertFalse($c->exists('a'));
        $this->assertFalse($c->exists('b'));
    }

    public function testAutoDetectReturnsString(): void
    {
        $c = new Cache(true);
        $this->assertIsString($c->load(true));
    }
}
