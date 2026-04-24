<?php

declare(strict_types=1);

namespace Tests\Unit\Db;

use DB\Jig;
use DB\Jig\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level coverage for DB\Jig\Mapper: field access, identity,
 * cast, copyfrom/copyto, fields, getiterator, triggers, navigation,
 * find/count, reset, dry.
 */
final class JigMapperTest extends TestCase
{
    private string $dir;
    private Jig $db;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3jigunit-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->dir, 0755, true);
        $this->db = new Jig($this->dir, Jig::FORMAT_JSON);
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

    // -- identity -----------------------------------------------------------

    public function testDbtypeReturnsJig(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertSame('Jig', $m->dbtype());
    }

    // -- field access -------------------------------------------------------

    public function testSetAndGetField(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'Alice';
        $this->assertSame('Alice', $m->name);
    }

    public function testExistsReturnsTrueForSetField(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->score = 42;
        $this->assertTrue($m->exists('score'));
    }

    public function testExistsReturnsFalseForUnsetField(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertFalse($m->exists('nonexistent'));
    }

    public function testSetReturnsFalseForIdField(): void
    {
        $m = new Mapper($this->db, 'items');
        $result = $m->set('_id', 'anything');
        $this->assertFalse($result);
    }

    public function testClearRemovesField(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->foo = 'bar';
        $this->assertTrue($m->exists('foo'));
        $m->clear('foo');
        $this->assertFalse($m->exists('foo'));
    }

    public function testClearIdIsNoOp(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'x';
        $m->save();
        $id = $m->_id;
        $m->clear('_id');
        $this->assertSame($id, $m->_id, 'Clearing _id must not wipe it');
    }

    public function testGetThrowsForUndefinedField(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->expectException(\Exception::class);
        $_ = $m->undeclared_field_xyz;
    }

    // -- dry / reset --------------------------------------------------------

    public function testDryIsTrueBeforeLoad(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertTrue($m->dry());
    }

    public function testDryIsFalseAfterLoad(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'Bob';
        $m->save();

        $m2 = new Mapper($this->db, 'items');
        $m2->load(['@name=?', 'Bob']);
        $this->assertFalse($m2->dry());
    }

    public function testResetMakesMapperDryAgain(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'Carol';
        $m->save();
        $m->load(['@name=?', 'Carol']);
        $this->assertFalse($m->dry());
        $m->reset();
        $this->assertTrue($m->dry());
    }

    // -- cast ---------------------------------------------------------------

    public function testCastReturnsDocumentPlusId(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'Dan';
        $m->age  = 25;
        $m->save();

        $arr = $m->cast();
        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('age', $arr);
        $this->assertArrayHasKey('_id', $arr);
        $this->assertSame('Dan', $arr['name']);
    }

    public function testCastWithExplicitObject(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->x = 10;
        $m->save();

        $m2 = new Mapper($this->db, 'items');
        $m2->load(['@x=?', 10]);

        $arr = $m->cast($m2);
        $this->assertArrayHasKey('x', $arr);
    }

    // -- fields -------------------------------------------------------------

    public function testFieldsReturnsDocumentKeys(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->alpha = 1;
        $m->beta  = 2;
        $keys = $m->fields();
        $this->assertContains('alpha', $keys);
        $this->assertContains('beta', $keys);
        $this->assertNotContains('_id', $keys);
    }

    // -- copyfrom / copyto --------------------------------------------------

    public function testCopyfromPopulatesFromArray(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->copyfrom(['name' => 'Eve', 'score' => 99]);
        $this->assertSame('Eve', $m->name);
        $this->assertSame(99, $m->score);
    }

    public function testCopyfromWithCallback(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->copyfrom(
            ['name' => 'Fred', 'score' => 5],
            fn ($data) => array_map('strtoupper', array_map('strval', $data))
        );
        $this->assertSame('FRED', $m->name);
    }

    public function testCopyfromHiveKey(): void
    {
        $f3 = \Base::instance();
        $f3->set('TEST_MAP', ['name' => 'Gina', 'score' => 77]);
        $m = new Mapper($this->db, 'items');
        $m->copyfrom('TEST_MAP');
        $this->assertSame('Gina', $m->name);
        $f3->clear('TEST_MAP');
    }

    public function testCopytoPopulatesHiveKey(): void
    {
        $f3 = \Base::instance();
        $m  = new Mapper($this->db, 'items');
        $m->title = 'Hello';
        $m->count = 3;
        $m->copyto('TEST_OUT');
        $this->assertSame('Hello', $f3->get('TEST_OUT.title'));
        $this->assertSame(3, $f3->get('TEST_OUT.count'));
        $f3->clear('TEST_OUT');
    }

    // -- getiterator --------------------------------------------------------

    public function testGetIteratorCoversAllFields(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->p = 1;
        $m->q = 2;
        $m->save();

        $arr = iterator_to_array($m->getiterator());
        $this->assertArrayHasKey('p', $arr);
        $this->assertArrayHasKey('q', $arr);
        $this->assertArrayHasKey('_id', $arr);
    }

    // -- find / count -------------------------------------------------------

    public function testFindReturnsMatchingRecords(): void
    {
        $m = new Mapper($this->db, 'words');
        $m->word = 'apple';
        $m->save();

        $m2 = new Mapper($this->db, 'words');
        $m2->word = 'banana';
        $m2->save();

        $m3 = new Mapper($this->db, 'words');
        $found = $m3->find(['@word=?', 'apple']);
        $this->assertCount(1, $found);
        $this->assertSame('apple', $found[0]->word);
    }

    public function testFindNullReturnsAllRecords(): void
    {
        $m = new Mapper($this->db, 'nums');
        for ($i = 1; $i <= 3; $i++) {
            $m->reset();
            $m->n = $i;
            $m->save();
        }
        $m2  = new Mapper($this->db, 'nums');
        $all = $m2->find();
        $this->assertCount(3, $all);
    }

    public function testCountMatchesFilteredFind(): void
    {
        $m = new Mapper($this->db, 'scores');
        foreach ([10, 20, 30] as $v) {
            $m->reset();
            $m->val = $v;
            $m->save();
        }
        $m2 = new Mapper($this->db, 'scores');
        $this->assertSame(1, $m2->count(['@val=?', 20]));
    }

    // -- triggers -----------------------------------------------------------

    public function testBeforeInsertCanCancelInsert(): void
    {
        $m = new Mapper($this->db, 'guarded');
        $m->beforeinsert(fn () => false);
        $m->name = 'blocked';
        $m->insert();

        $m2   = new Mapper($this->db, 'guarded');
        $rows = $m2->find();
        $this->assertCount(0, $rows);
    }

    public function testAfterInsertFiresWithId(): void
    {
        $fired = false;
        $m = new Mapper($this->db, 'tagged');
        $m->afterinsert(function ($map) use (&$fired) {
            $fired = true;
        });
        $m->label = 'test';
        $m->insert();
        $this->assertTrue($fired);
    }

    public function testBeforeUpdateCanCancelUpdate(): void
    {
        $m = new Mapper($this->db, 'readonly');
        $m->val = 1;
        $m->save();

        $m->beforeupdate(fn () => false);
        $m->val = 999;
        $m->update();

        $m2 = new Mapper($this->db, 'readonly');
        $m2->load();
        $this->assertSame(1, $m2->val);
    }

    public function testAfterUpdateFiresAfterSave(): void
    {
        $fired = false;
        $m = new Mapper($this->db, 'audit');
        $m->val = 1;
        $m->save();

        $m->afterupdate(function () use (&$fired) {
            $fired = true;
        });
        $m->val = 2;
        $m->update();
        $this->assertTrue($fired);
    }

    public function testAfterEraseFiresOnDelete(): void
    {
        $fired = false;
        $m = new Mapper($this->db, 'trash');
        $m->name = 'gone';
        $m->save();

        $m->aftererase(function () use (&$fired) {
            $fired = true;
        });
        $m->erase();
        $this->assertTrue($fired);
    }

    // -- navigation ---------------------------------------------------------

    public function testSkipNavigatesForwardAndBack(): void
    {
        $m = new Mapper($this->db, 'seq');
        foreach (['a', 'b', 'c'] as $v) {
            $m->reset();
            $m->v = $v;
            $m->save();
        }

        $m2 = new Mapper($this->db, 'seq');
        $m2->load(null, ['order' => 'v']);
        $this->assertSame('a', $m2->v);

        $m2->next();
        $this->assertSame('b', $m2->v);

        $m2->prev();
        $this->assertSame('a', $m2->v);
    }

    public function testFirstAndLastNavigate(): void
    {
        $m = new Mapper($this->db, 'seq2');
        foreach (['x', 'y', 'z'] as $v) {
            $m->reset();
            $m->v = $v;
            $m->save();
        }

        $m2 = new Mapper($this->db, 'seq2');
        $m2->load(null, ['order' => 'v']);
        $m2->last();
        $this->assertSame('z', $m2->v);

        $m2->first();
        $this->assertSame('x', $m2->v);
    }

    // -- Jig store-level ----------------------------------------------------

    public function testJigDirReturnsDirectory(): void
    {
        $this->assertSame($this->dir, $this->db->dir());
    }

    public function testJigUuidIsNonEmptyString(): void
    {
        $uuid = $this->db->uuid();
        $this->assertIsString($uuid);
        $this->assertNotEmpty($uuid);
    }

    public function testJigUuidIsDeterministic(): void
    {
        $this->assertSame($this->db->uuid(), $this->db->uuid());
    }

    public function testJigLogAndJot(): void
    {
        $this->db->jot('first entry');
        $log = $this->db->log();
        $this->assertIsString($log);
        $this->assertStringContainsString('first entry', $log);
    }

    public function testJigLogDisable(): void
    {
        $this->db->jot('entry');
        $this->db->log(false);
        // log(false) resets the internal log state to FALSE; log() returns it directly
        $this->assertFalse($this->db->log());
    }

    public function testJigJotIgnoresEmptyString(): void
    {
        $logBefore = $this->db->log();
        $this->db->jot('');
        $this->assertSame($logBefore, $this->db->log());
    }

    public function testJigDropClearsFiles(): void
    {
        $m = new \DB\Jig\Mapper($this->db, 'toclean');
        $m->val = 1;
        $m->save();
        // The collection file must exist after save
        $this->assertTrue(is_file($this->dir . 'toclean'));
        $this->db->drop();
        // All files removed
        $this->assertFalse(is_file($this->dir . 'toclean'));
    }

    public function testJigDropOnEmptyStoreDoesNotThrow(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3jig-empty-' . uniqid() . DIRECTORY_SEPARATOR;
        $db = new \DB\Jig($dir, \DB\Jig::FORMAT_JSON);
        $db->drop();
        // clean up
        @rmdir($dir);
        $this->addToAssertionCount(1);
    }

    public function testJigSerializedFormat(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3jig-ser-' . uniqid() . DIRECTORY_SEPARATOR;
        $db = new \DB\Jig($dir, \DB\Jig::FORMAT_Serialized);
        $m = new \DB\Jig\Mapper($db, 'ser');
        $m->value = 'serialized';
        $m->save();

        // Reload from a new mapper instance to force file read
        $m2 = new \DB\Jig\Mapper($db, 'ser');
        $found = $m2->find();
        $this->assertCount(1, $found);
        $this->assertSame('serialized', $found[0]->value);

        foreach (glob($dir . '*') as $f) @unlink($f);
        @rmdir($dir);
    }

    public function testJigInMemoryWithNullDir(): void
    {
        $db = new \DB\Jig(null, \DB\Jig::FORMAT_JSON);
        $m = new \DB\Jig\Mapper($db, 'mem');
        $m->x = 42;
        $m->save();
        $m2 = new \DB\Jig\Mapper($db, 'mem');
        $rows = $m2->find();
        $this->assertCount(1, $rows);
        $this->assertSame(42, $rows[0]->x);
    }
}
