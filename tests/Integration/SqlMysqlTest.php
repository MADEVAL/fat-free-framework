<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class SqlMysqlTest extends TestCase
{
    private static ?\DB\SQL $db = null;
    private static string $dbName;

    public static function setUpBeforeClass(): void
    {
        self::$dbName = getenv('DB_MYSQL_NAME') ?: 'fatfree_test';
        $baseDsn = getenv('DB_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
        $user = getenv('DB_MYSQL_USER') ?: 'root';
        $pass = getenv('DB_MYSQL_PASS') ?: 'root';

        try {
            // Connect without dbname to be able to (re)create it
            $admin = new \PDO($baseDsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $admin->exec('CREATE DATABASE IF NOT EXISTS `' . self::$dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
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
            $this->markTestSkipped('MySQL not reachable: ' . (getenv('DB_MYSQL_DSN') ?: 'default DSN'));
        }
        self::$db->exec('DROP TABLE IF EXISTS ff_users');
        self::$db->exec(
            'CREATE TABLE ff_users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                age INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function testDriver(): void
    {
        $this->assertSame('mysql', self::$db->driver());
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

        $m2->age = 29;
        $m2->save();

        $m3 = new \DB\SQL\Mapper(self::$db, 'ff_users');
        $m3->load(['id=?', $m->id]);
        $this->assertSame(29, (int) $m3->age);

        $m3->erase();
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
