<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Folder-cache backend round-trip tests (no external services needed).
 */
final class CacheTest extends TestCase
{
    private string $dir;
    private \Cache $cache;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/fatfree_cache_' . uniqid('', true) . '/';
        $this->cache = new \Cache('folder=' . $this->dir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
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
}
