<?php

declare(strict_types=1);

namespace Tests\Unit\Db;

use DB\SQL;
use PHPUnit\Framework\TestCase;

/**
 * DB\SQL utility methods not exercised by the integration tests:
 * pdo(), version(), quotekey(), name(), uuid(), count(), log(),
 * exists(), schema() and the __call PDO proxy.
 */
final class SqlTest extends TestCase
{
    private SQL $db;

    protected function setUp(): void
    {
        $this->db = new SQL('sqlite::memory:');
        $this->db->exec(
            'CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, qty INTEGER)'
        );
        $this->db->exec('INSERT INTO items (name, qty) VALUES (?,?)', ['apple', 5]);
        $this->db->exec('INSERT INTO items (name, qty) VALUES (?,?)', ['banana', 12]);
    }

    public function testPdoReturnsPdoInstance(): void
    {
        $this->assertInstanceOf(\PDO::class, $this->db->pdo());
    }

    public function testVersionIsNonEmptyString(): void
    {
        $v = $this->db->version();
        $this->assertIsString($v);
        $this->assertNotEmpty($v);
    }

    public function testQuotekeyWrapsIdentifier(): void
    {
        $q = $this->db->quotekey('col_name');
        // SQLite uses backticks
        $this->assertSame('`col_name`', $q);
    }

    public function testQuotekeyWithDotSplits(): void
    {
        $q = $this->db->quotekey('table.col');
        $this->assertSame('`table`.`col`', $q);
    }

    public function testQuotekeyNoSplitKeepsDot(): void
    {
        $q = $this->db->quotekey('table.col', false);
        $this->assertSame('`table.col`', $q);
    }

    public function testNameReturnsNullOrStringForSqlite(): void
    {
        // SQLite ':memory:' has no schema name; name() returns null for in-memory DBs
        $name = $this->db->name();
        $this->assertTrue($name === null || is_string($name));
    }

    public function testUuidIsNonEmptyString(): void
    {
        $uuid = $this->db->uuid();
        $this->assertIsString($uuid);
        $this->assertNotEmpty($uuid);
    }

    public function testUuidIsSameObjectEachCall(): void
    {
        // uuid() is the DB object's own identity UUID, not a fresh value
        $this->assertSame($this->db->uuid(), $this->db->uuid());
    }

    public function testCountReturnsAffectedRows(): void
    {
        $this->db->exec('UPDATE items SET qty=?', [99]);
        $this->assertSame(2, $this->db->count());
    }

    public function testLogContainsExecutedSql(): void
    {
        $this->db->exec('SELECT name FROM items WHERE id=?', [1]);
        $log = $this->db->log();
        $this->assertIsString($log);
        $this->assertStringContainsString('SELECT', $log);
    }

    public function testExistsReturnsTrueForExistingTable(): void
    {
        $this->assertTrue($this->db->exists('items'));
    }

    public function testExistsReturnsFalseForMissingTable(): void
    {
        $this->assertFalse($this->db->exists('no_such_table_xyz'));
    }

    public function testSchemaReturnsFieldArray(): void
    {
        $schema = $this->db->schema('items');
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('id', $schema);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('qty', $schema);
    }

    public function testSchemaFieldHasExpectedKeys(): void
    {
        $schema = $this->db->schema('items');
        $nameField = $schema['name'];
        $this->assertArrayHasKey('type', $nameField);
        $this->assertArrayHasKey('nullable', $nameField);
        $this->assertArrayHasKey('pkey', $nameField);
        $this->assertFalse($nameField['nullable']);
    }

    public function testCallProxiesToPdo(): void
    {
        // __call forwards to PDO: lastInsertId() is a valid PDO method
        $this->db->exec('INSERT INTO items (name, qty) VALUES (?,?)', ['cherry', 1]);
        $id = $this->db->lastinsertid();
        $this->assertIsString($id);
        $this->assertGreaterThan(0, (int) $id);
    }

    public function testQuoteEscapesStringValue(): void
    {
        $quoted = $this->db->quote("it's a test");
        $this->assertStringContainsString("it", $quoted);
        // PDO::quote wraps with single quotes
        $this->assertStringStartsWith("'", $quoted);
    }

    public function testTransactionBeginCommit(): void
    {
        $this->assertFalse($this->db->trans());
        $this->db->begin();
        $this->assertTrue($this->db->trans());
        $this->db->exec('INSERT INTO items (name, qty) VALUES (?,?)', ['mango', 3]);
        $this->db->commit();
        $this->assertFalse($this->db->trans());

        $rows = $this->db->exec('SELECT name FROM items WHERE name=?', ['mango']);
        $this->assertCount(1, $rows);
    }

    public function testTransactionRollback(): void
    {
        $this->db->begin();
        $this->db->exec('INSERT INTO items (name, qty) VALUES (?,?)', ['ghost', 99]);
        $this->db->rollback();
        $this->assertFalse($this->db->trans());

        $rows = $this->db->exec('SELECT name FROM items WHERE name=?', ['ghost']);
        $this->assertCount(0, $rows);
    }

    public function testRollbackWhenNoTransactionReturnsFalse(): void
    {
        $this->assertFalse($this->db->trans());
        $result = $this->db->rollback();
        $this->assertFalse($result);
    }

    public function testCommitWhenNoTransactionReturnsFalse(): void
    {
        $result = $this->db->commit();
        $this->assertFalse($result);
    }

    public function testTypeMapsPrimitivesToPdoConstants(): void
    {
        $this->assertSame(\PDO::PARAM_NULL, $this->db->type(null));
        $this->assertSame(\PDO::PARAM_BOOL, $this->db->type(true));
        $this->assertSame(\PDO::PARAM_INT, $this->db->type(42));
        $this->assertSame(\PDO::PARAM_STR, $this->db->type('text'));
        // PHP's gettype() returns 'double' for floats, so the 'float' switch case
        // is never matched and floats fall through to the default PARAM_STR branch.
        $this->assertSame(\PDO::PARAM_STR, $this->db->type(3.14));
    }

    public function testValueCastsToExpectedPhpTypes(): void
    {
        $this->assertNull($this->db->value(\PDO::PARAM_NULL, 'anything'));
        $this->assertSame(42, $this->db->value(\PDO::PARAM_INT, '42'));
        $this->assertTrue($this->db->value(\PDO::PARAM_BOOL, 1));
        $this->assertSame('hello', $this->db->value(\PDO::PARAM_STR, 'hello'));
        // PARAM_FLOAT just normalises the decimal separator
        $out = $this->db->value(\DB\SQL::PARAM_FLOAT, '1.5');
        $this->assertSame('1.5', $out);
    }

    public function testAutoTransactionBatchExec(): void
    {
        // exec() with array of commands auto-wraps in a transaction
        $cmds = [
            'INSERT INTO items (name, qty) VALUES (\'peach\', 1)',
            'INSERT INTO items (name, qty) VALUES (\'plum\', 2)',
        ];
        $this->db->exec($cmds);
        $rows = $this->db->exec('SELECT COUNT(*) AS n FROM items WHERE name IN (\'peach\',\'plum\')');
        $this->assertSame(2, (int) $rows[0]['n']);
    }
}
