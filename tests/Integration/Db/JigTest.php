<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use DB\Jig;
use DB\Jig\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end DB\Jig CRUD over a temporary directory store.
 * Exercises both the Jig store and the Cursor base via Jig\Mapper.
 */
final class JigTest extends TestCase
{
    private string $dir;
    private Jig $db;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3jig-' . uniqid() . DIRECTORY_SEPARATOR;
        $this->db = new Jig($this->dir, Jig::FORMAT_JSON);
    }

    protected function tearDown(): void
    {
        // Remove store and any flat files inside.
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '*') as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
    }

    public function testInsertAssignsIdAndPersists(): void
    {
        $m = new Mapper($this->db, 'people');
        $m->name = 'Alice';
        $m->age = 30;
        $m->save();
        $this->assertNotEmpty($m->_id);

        $m2 = new Mapper($this->db, 'people');
        $found = $m2->find(['@name=?', 'Alice']);
        $this->assertCount(1, $found);
        $this->assertSame('Alice', $found[0]->name);
        $this->assertSame(30, $found[0]->age);
    }

    public function testUpdateReplacesValues(): void
    {
        $m = new Mapper($this->db, 'people');
        $m->name = 'Bob';
        $m->save();
        $id = $m->_id;

        $m->name = 'Robert';
        $m->save();

        $m2 = new Mapper($this->db, 'people');
        $loaded = $m2->load(['@_id=?', $id]);
        $this->assertNotFalse($loaded);
        $this->assertSame('Robert', $m2->name);
    }

    public function testEraseRemovesRecord(): void
    {
        $m = new Mapper($this->db, 'people');
        $m->name = 'Carol';
        $m->save();

        $m->erase();
        $m2 = new Mapper($this->db, 'people');
        $this->assertSame(0, $m2->count(['@name=?', 'Carol']));
    }

    public function testCountAndPaginate(): void
    {
        $m = new Mapper($this->db, 'people');
        for ($i = 1; $i <= 7; ++$i) {
            $m->reset();
            $m->name = "p$i";
            $m->save();
        }
        $m2 = new Mapper($this->db, 'people');
        $this->assertSame(7, $m2->count());

        $page = $m2->paginate(0, 3);
        $this->assertSame(7, $page['total']);
        $this->assertSame(3, $page['limit']);
        $this->assertSame(3, $page['count']);
        $this->assertCount(3, $page['subset']);
    }

    public function testDryAndLoadedSemantics(): void
    {
        $m = new Mapper($this->db, 'people');
        $this->assertTrue($m->dry());
        $m->name = 'Dave';
        $m->save();

        $m2 = new Mapper($this->db, 'people');
        $m2->load(['@name=?', 'Dave']);
        $this->assertFalse($m2->dry());
    }

    public function testTriggerOnInsert(): void
    {
        $m = new Mapper($this->db, 'people');
        $marker = (object) ['called' => false];
        $m->beforeinsert(function ($self) use ($marker) {
            $marker->called = true;
        });
        $m->name = 'Eve';
        $m->save();
        $this->assertTrue($marker->called);
    }
}
