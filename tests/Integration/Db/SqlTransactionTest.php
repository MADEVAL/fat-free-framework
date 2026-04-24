<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use DB\SQL;
use PHPUnit\Framework\TestCase;

/**
 * SQL transaction handling: begin/commit/rollback, schema introspection.
 * Uses SQLite in-memory.
 */
final class SqlTransactionTest extends TestCase
{
    private SQL $db;

    protected function setUp(): void
    {
        $this->db = new SQL('sqlite::memory:');
        $this->db->exec('CREATE TABLE accounts (id INTEGER PRIMARY KEY, balance INTEGER NOT NULL)');
        $this->db->exec('INSERT INTO accounts (id,balance) VALUES (1,100), (2,50)');
    }

    public function testCommitPersists(): void
    {
        $this->db->begin();
        $this->db->exec('UPDATE accounts SET balance=balance-10 WHERE id=1');
        $this->db->exec('UPDATE accounts SET balance=balance+10 WHERE id=2');
        $this->db->commit();

        $rows = $this->db->exec('SELECT balance FROM accounts ORDER BY id');
        $this->assertSame(90, (int) $rows[0]['balance']);
        $this->assertSame(60, (int) $rows[1]['balance']);
    }

    public function testRollbackReverts(): void
    {
        $this->db->begin();
        $this->db->exec('UPDATE accounts SET balance=0 WHERE id=1');
        $this->db->rollback();

        $rows = $this->db->exec('SELECT balance FROM accounts WHERE id=1');
        $this->assertSame(100, (int) $rows[0]['balance']);
    }

    public function testSchemaIntrospection(): void
    {
        $schema = $this->db->schema('accounts');
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('id', $schema);
        $this->assertArrayHasKey('balance', $schema);
    }

    public function testCount(): void
    {
        // count() returns rows from the last executed query.
        $this->db->exec('SELECT * FROM accounts');
        $this->assertSame(2, $this->db->count());
    }

    public function testLog(): void
    {
        $this->db->exec('SELECT 1');
        $this->assertNotEmpty($this->db->log());
    }

    public function testNamedPlaceholders(): void
    {
        $rows = $this->db->exec(
            'SELECT * FROM accounts WHERE id=:id',
            [':id' => 1]
        );
        $this->assertCount(1, $rows);
    }

    public function testQuoteEscapes(): void
    {
        $q = $this->db->quote("O'Hara");
        $this->assertStringContainsString("O''Hara", $q);
    }

    public function testNameAndType(): void
    {
        $this->assertSame('sqlite', $this->db->driver());
        $this->assertNotEmpty($this->db->version());
    }
}
