<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use DB\SQL;
use DB\SQL\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * Advanced SQL Mapper behaviors exercised against an in-memory SQLite DB:
 * pagination, group-by with having, ordering and joins via raw exec.
 */
final class SqlMapperAdvancedTest extends TestCase
{
    private SQL $db;

    protected function setUp(): void
    {
        $this->db = new SQL('sqlite::memory:');
        $this->db->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                name TEXT NOT NULL,
                price REAL NOT NULL
            )'
        );
        $rows = [
            ['fruit', 'apple', 1.0],
            ['fruit', 'banana', 0.5],
            ['fruit', 'cherry', 2.5],
            ['veg',   'carrot', 0.8],
            ['veg',   'potato', 0.3],
        ];
        $stmt = 'INSERT INTO products (category,name,price) VALUES (?,?,?)';
        foreach ($rows as $r) {
            $this->db->exec($stmt, $r);
        }
    }

    public function testPaginateMetadata(): void
    {
        $m = new Mapper($this->db, 'products');
        $page = $m->paginate(0, 2, null, ['order' => 'id']);
        $this->assertSame(5, $page['total']);
        $this->assertSame(2, $page['limit']);
        $this->assertSame(3, $page['count']);
        $this->assertCount(2, $page['subset']);
    }

    public function testOrderByAndLimit(): void
    {
        $m = new Mapper($this->db, 'products');
        $rows = $m->find(null, ['order' => 'price DESC', 'limit' => 2]);
        $this->assertCount(2, $rows);
        $this->assertSame('cherry', $rows[0]->name);
    }

    public function testGroupByWithHaving(): void
    {
        $m = new Mapper($this->db, 'products');
        // F3 SQL Mapper expects HAVING embedded in the group option string.
        $rows = $m->find(null, [
            'group'  => 'category HAVING COUNT(*) > 2',
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame('fruit', $rows[0]->category);
    }

    public function testFilterPlaceholders(): void
    {
        $m = new Mapper($this->db, 'products');
        $rows = $m->find(['category=? AND price<?', 'fruit', 1.5]);
        $names = array_map(fn ($r) => $r->name, $rows);
        sort($names);
        $this->assertSame(['apple', 'banana'], $names);
    }

    public function testCount(): void
    {
        $m = new Mapper($this->db, 'products');
        $this->assertSame(5, $m->count());
        $this->assertSame(3, $m->count(['category=?', 'fruit']));
    }
}
