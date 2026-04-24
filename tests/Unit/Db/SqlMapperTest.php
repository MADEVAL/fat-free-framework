<?php

declare(strict_types=1);

namespace Tests\Unit\Db;

use Base;
use DB\SQL;
use DB\SQL\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * DB\SQL\Mapper behaviors not exercised by integration tests:
 * dbtype, table, changed, field exists, fields, required, cast,
 * copyfrom, copyto, dry/reset, navigation, getiterator, alias,
 * schema accessor, updateAll.
 */
final class SqlMapperTest extends TestCase
{
    private SQL $db;

    protected function setUp(): void
    {
        $this->db = new SQL('sqlite::memory:');
        $this->db->exec(
            'CREATE TABLE items (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT NOT NULL,
                qty   INTEGER DEFAULT 0,
                price REAL
            )'
        );
        $this->db->exec('INSERT INTO items (name, qty, price) VALUES (?,?,?)', ['apple',  5, 1.50]);
        $this->db->exec('INSERT INTO items (name, qty, price) VALUES (?,?,?)', ['banana', 12, 0.75]);
    }

    // -- identity -----------------------------------------------------------

    public function testDbtypeReturnsSql(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertSame('SQL', $m->dbtype());
    }

    public function testTableReturnsName(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertSame('items', $m->table());
    }

    // -- change tracking ----------------------------------------------------

    public function testChangedReturnsFalseOnFreshLoad(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $this->assertFalse($m->changed());
    }

    public function testChangedReturnsTrueAfterFieldWrite(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $m->qty = 99;
        $this->assertTrue($m->changed());
    }

    public function testChangedForSpecificModifiedField(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $m->qty = 99;
        $this->assertTrue($m->changed('qty'));
        $this->assertFalse($m->changed('name'));
    }

    // -- field existence ----------------------------------------------------

    public function testExistsReturnsTrueForDefinedField(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertTrue($m->exists('name'));
        $this->assertTrue($m->exists('qty'));
        $this->assertTrue($m->exists('price'));
    }

    public function testExistsReturnsFalseForUnknownField(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertFalse($m->exists('nonexistent'));
    }

    // -- fields / required / schema -----------------------------------------

    public function testFieldsReturnsAllColumnNames(): void
    {
        $m = new Mapper($this->db, 'items');
        $f = $m->fields();
        $this->assertContains('id',    $f);
        $this->assertContains('name',  $f);
        $this->assertContains('qty',   $f);
        $this->assertContains('price', $f);
    }

    public function testRequiredTrueForNotNullColumn(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertTrue($m->required('name'));
    }

    public function testRequiredFalseForNullableColumn(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertFalse($m->required('price'));
    }

    public function testSchemaReturnsFieldsArray(): void
    {
        $m = new Mapper($this->db, 'items');
        $schema = $m->schema();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('qty',  $schema);
    }

    // -- cast / copyfrom / copyto -------------------------------------------

    public function testCastReturnsAssocWithCurrentValues(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $cast = $m->cast();
        $this->assertIsArray($cast);
        $this->assertSame('apple', $cast['name']);
        $this->assertSame(5, (int) $cast['qty']);
    }

    public function testCopyfromFillsFieldsFromArray(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->copyfrom(['name' => 'cherry', 'qty' => 3, 'price' => 2.5]);
        $this->assertSame('cherry', $m->name);
        $this->assertSame(3, (int) $m->qty);
    }

    public function testCopytoWritesFieldsToHiveKey(): void
    {
        $f3 = Base::instance();
        $m  = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $m->copyto('TEST_COPYTO');
        $this->assertSame('apple', $f3->get('TEST_COPYTO.name'));
        $this->assertSame(5, (int) $f3->get('TEST_COPYTO.qty'));
        $f3->clear('TEST_COPYTO');
    }

    // -- dry / reset --------------------------------------------------------

    public function testDryReturnsTrueBeforeLoad(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertTrue($m->dry());
    }

    public function testDryReturnsFalseAfterLoad(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $this->assertFalse($m->dry());
    }

    public function testResetClearsCurrentRecord(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $m->reset();
        $this->assertTrue($m->dry());
        $this->assertNull($m->name);
    }

    // -- insert / update / erase --------------------------------------------

    public function testInsertAddsRow(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'grape';
        $m->qty  = 7;
        $m->save();
        $this->assertSame(3, (int) $this->db->exec('SELECT COUNT(*) AS c FROM items')[0]['c']);
    }

    public function testEraseRemovesCurrentRecord(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->name = 'temp';
        $m->qty  = 1;
        $m->save();
        $m->erase();
        $this->assertSame(2, (int) $this->db->exec('SELECT COUNT(*) AS c FROM items')[0]['c']);
    }

    public function testUpdateAllChangesMatchingRows(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $m->qty = 100;
        $m->updateAll(['name!=?', 'banana']);
        $rows = $this->db->exec('SELECT qty FROM items WHERE name=?', ['apple']);
        $this->assertSame(100, (int) $rows[0]['qty']);
        $banana = $this->db->exec('SELECT qty FROM items WHERE name=?', ['banana']);
        $this->assertNotSame(100, (int) $banana[0]['qty']);
    }

    // -- navigation ---------------------------------------------------------

    public function testNextAndPrevNavigation(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(null, ['order' => 'id ASC']);
        $this->assertSame('apple', $m->name);
        $m->next();
        $this->assertSame('banana', $m->name);
        $m->prev();
        $this->assertSame('apple', $m->name);
    }

    public function testFirstAndLastNavigation(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(null, ['order' => 'id ASC']);
        $m->last();
        $this->assertSame('banana', $m->name);
        $m->first();
        $this->assertSame('apple', $m->name);
    }

    public function testLoadedCountMatchesResultSet(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(null);
        $this->assertSame(2, $m->loaded());
    }

    // -- iterator / alias ---------------------------------------------------

    public function testGetiteratorReturnsTraversable(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->load(['name=?', 'apple']);
        $iter = $m->getiterator();
        $this->assertInstanceOf(\Traversable::class, $iter);
        $data = iterator_to_array($iter);
        $this->assertArrayHasKey('name',  $data);
        $this->assertSame('apple', $data['name']);
    }

    public function testAliasDoesNotBreakFind(): void
    {
        $m = new Mapper($this->db, 'items');
        $m->alias('i');
        $rows = $m->find(null, ['limit' => 1]);
        $this->assertCount(1, $rows);
    }

    // -- count / find / findone ---------------------------------------------

    public function testCountWithoutFilterReturnsTotalRows(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertSame(2, $m->count());
    }

    public function testCountWithFilterReturnsMatchingRows(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertSame(1, $m->count(['name=?', 'apple']));
    }

    public function testFindWithOrderAndLimitRespectsBoth(): void
    {
        $m = new Mapper($this->db, 'items');
        $rows = $m->find(null, ['order' => 'qty DESC', 'limit' => 1]);
        $this->assertCount(1, $rows);
        $this->assertSame('banana', $rows[0]->name);
    }

    public function testFindoneReturnsFirstMatchingRecord(): void
    {
        $m   = new Mapper($this->db, 'items');
        $row = $m->findone(['name=?', 'apple']);
        $this->assertNotFalse($row);
        $this->assertSame('apple', $row->name);
    }

    public function testFindoneReturnsFalseWhenNoMatch(): void
    {
        $m = new Mapper($this->db, 'items');
        $this->assertFalse($m->findone(['name=?', 'nonexistent_xyz']));
    }
}
