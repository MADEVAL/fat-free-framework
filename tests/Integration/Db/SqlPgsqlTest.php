<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use PHPUnit\Framework\TestCase;

final class SqlPgsqlTest extends TestCase
{
    private static ?\DB\SQL $db = null;
    private static string $dbName;

    public static function setUpBeforeClass(): void
    {
        self::$dbName = getenv('DB_PGSQL_NAME') ?: 'fatfree_test';
        $baseDsn = getenv('DB_PGSQL_DSN') ?: 'pgsql:host=127.0.0.1;port=5432';
        $user = getenv('DB_PGSQL_USER') ?: 'postgres';
        $pass = getenv('DB_PGSQL_PASS') ?: 'postgres';

        try {
            // Connect to default 'postgres' DB to (re)create the test DB
            $admin = new \PDO($baseDsn . ';dbname=postgres', $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $stmt = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $stmt->execute([self::$dbName]);
            if (!$stmt->fetchColumn()) {
                $admin->exec('CREATE DATABASE "' . self::$dbName . '" ENCODING UTF8');
            }
            $admin = null;

            $dsn = $baseDsn . ';dbname=' . self::$dbName;
            self::$db = new \DB\SQL($dsn, $user, $pass);
        } catch (\Throwable $e) {
            self::$db = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('PostgreSQL not reachable: ' . (getenv('DB_PGSQL_DSN') ?: 'default DSN'));
        }
        self::$db->exec('DROP TABLE IF EXISTS ff_users');
        self::$db->exec(
            'CREATE TABLE ff_users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                age INT NULL
            )'
        );
    }

    public function testDriver(): void
    {
        $this->assertSame('pgsql', self::$db->driver());
    }

    public function testCrud(): void
    {
        self::$db->exec('INSERT INTO ff_users (name, age) VALUES (?, ?)', ['Алиса', 30]);
        self::$db->exec('INSERT INTO ff_users (name, age) VALUES (?, ?)', ['Боб', 25]);

        $rows = self::$db->exec('SELECT * FROM ff_users ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertSame('Алиса', $rows[0]['name']);
    }

    public function testMapperCrud(): void
    {
        $m = new \DB\SQL\Mapper(self::$db, 'ff_users');
        $m->name = 'Eve';
        $m->age = 28;
        $m->save();
        $this->assertGreaterThan(0, (int) $m->id);

        $m2 = new \DB\SQL\Mapper(self::$db, 'ff_users');
        $m2->load(['id=?', $m->id]);
        $this->assertSame('Eve', $m2->name);

        $m2->erase();
        $count = self::$db->exec('SELECT COUNT(*) AS c FROM ff_users');
        $this->assertSame(0, (int) $count[0]['c']);
    }

    public function testTransactionRollback(): void
    {
        self::$db->begin();
        self::$db->exec('INSERT INTO ff_users (name, age) VALUES (?, ?)', ['X', 1]);
        self::$db->rollback();
        $count = self::$db->exec('SELECT COUNT(*) AS c FROM ff_users');
        $this->assertSame(0, (int) $count[0]['c']);
    }

    public static function tearDownAfterClass(): void
    {
        self::$db = null;
    }
}
