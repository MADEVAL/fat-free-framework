<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use PHPUnit\Framework\TestCase;

final class MongoTest extends TestCase
{
    private static ?\DB\Mongo $db = null;

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('mongodb')) {
            return;
        }
        $uri = getenv('DB_MONGO_URI') ?: 'mongodb://localhost:27017/';
        $name = getenv('DB_MONGO_NAME') ?: 'fatfree_test';
        try {
            // Probe connectivity *before* instantiating DB\Mongo (whose ctor
            // issues a profile command that would otherwise hang/throw).
            $manager = new \MongoDB\Driver\Manager(
                rtrim($uri, '/') . '/?serverSelectionTimeoutMS=1500'
            );
            $manager->executeCommand('admin', new \MongoDB\Driver\Command(['ping' => 1]));
            self::$db = new \DB\Mongo($uri, $name);
        } catch (\Throwable $e) {
            self::$db = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('MongoDB not reachable: ' . (getenv('DB_MONGO_URI') ?: 'default URI'));
        }
        self::$db->selectcollection('ff_users')->drop();
    }

    public function testMapperInsertAndLoad(): void
    {
        $m = new \DB\Mongo\Mapper(self::$db, 'ff_users');
        $m->name = 'Alice';
        $m->age = 30;
        $m->save();

        $m2 = new \DB\Mongo\Mapper(self::$db, 'ff_users');
        $m2->load(['name' => 'Alice']);
        $this->assertSame('Alice', $m2->name);
        $this->assertSame(30, (int) $m2->age);
    }

    public function testMapperFindCount(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $m = new \DB\Mongo\Mapper(self::$db, 'ff_users');
            $m->name = 'User' . $i;
            $m->age = 20 + $i;
            $m->save();
        }
        $m = new \DB\Mongo\Mapper(self::$db, 'ff_users');
        $this->assertSame(5, $m->count());

        $found = $m->find(['age' => ['$gte' => 23]]);
        $this->assertCount(2, $found);
    }

    public function testMapperUpdateAndErase(): void
    {
        $m = new \DB\Mongo\Mapper(self::$db, 'ff_users');
        $m->name = 'Bob';
        $m->age = 25;
        $m->save();

        $m2 = new \DB\Mongo\Mapper(self::$db, 'ff_users');
        $m2->load(['name' => 'Bob']);
        $m2->age = 26;
        $m2->save();

        $m3 = new \DB\Mongo\Mapper(self::$db, 'ff_users');
        $m3->load(['name' => 'Bob']);
        $this->assertSame(26, (int) $m3->age);

        $m3->erase();
        $count = (new \DB\Mongo\Mapper(self::$db, 'ff_users'))->count();
        $this->assertSame(0, $count);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db !== null) {
            self::$db->selectcollection('ff_users')->drop();
        }
        self::$db = null;
    }
}
