<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use PHPUnit\Framework\TestCase;

final class SqlSqliteTest extends TestCase
{
    private \DB\SQL $db;

    protected function setUp(): void
    {
        $this->db = new \DB\SQL('sqlite::memory:');
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER)');
        $this->db->exec('INSERT INTO users (name, age) VALUES (?, ?)', ['Alice', 30]);
        $this->db->exec('INSERT INTO users (name, age) VALUES (?, ?)', ['Bob', 25]);
    }

    public function testEngineDetected(): void
    {
        $this->assertSame('sqlite', $this->db->driver());
    }

    public function testExecReturnsRows(): void
    {
        $rows = $this->db->exec('SELECT * FROM users ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame(30, (int) $rows[0]['age']);
    }

    public function testParameterBinding(): void
    {
        $rows = $this->db->exec('SELECT name FROM users WHERE age > ?', [26]);
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testTransactionCommit(): void
    {
        $this->db->begin();
        $this->db->exec('INSERT INTO users (name, age) VALUES (?, ?)', ['Carol', 22]);
        $this->db->commit();
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM users');
        $this->assertSame(3, (int) $rows[0]['c']);
    }

    public function testTransactionRollback(): void
    {
        $this->db->begin();
        $this->db->exec('INSERT INTO users (name, age) VALUES (?, ?)', ['Dave', 40]);
        $this->db->rollback();
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM users');
        $this->assertSame(2, (int) $rows[0]['c']);
    }

    public function testMapper(): void
    {
        $mapper = new \DB\SQL\Mapper($this->db, 'users');
        $mapper->load(['name=?', 'Alice']);
        $this->assertSame('Alice', $mapper->name);
        $this->assertSame(30, (int) $mapper->age);

        $mapper->reset();
        $mapper->name = 'Eve';
        $mapper->age = 28;
        $mapper->save();

        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM users');
        $this->assertSame(3, (int) $rows[0]['c']);
    }
}
