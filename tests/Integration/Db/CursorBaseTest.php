<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use DB\Jig;
use DB\Jig\Mapper as JigMapper;
use PHPUnit\Framework\TestCase;

/**
 * Cursor base behavior exercised through the Jig mapper subclass.
 */
final class CursorBaseTest extends TestCase
{
    private string $dir;
    private Jig $db;
    private JigMapper $m;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jc-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->dir, 0777, true);
        $this->db = new Jig($this->dir, Jig::FORMAT_JSON);
        $this->m = new JigMapper($this->db, 'rows');
        for ($i = 1; $i <= 5; $i++) {
            $row = clone $this->m;
            $row->n = $i;
            $row->save();
        }
    }

    protected function tearDown(): void
    {
        $this->db->drop();
        foreach (glob($this->dir . '*') as $f) @unlink($f);
        @rmdir($this->dir);
    }

    public function testFindOneReturnsFirstHit(): void
    {
        $hit = $this->m->findone(['@n=?', 3]);
        $this->assertNotFalse($hit);
        $this->assertSame(3, $hit->n);
    }

    public function testPaginateProducesExpectedShape(): void
    {
        $page = $this->m->paginate(0, 2);
        $this->assertSame(5, $page['total']);
        $this->assertSame(2, $page['limit']);
        $this->assertSame(3, $page['count']);
        $this->assertSame(0, $page['pos']);
        $this->assertCount(2, $page['subset']);
    }

    public function testLoadFirstLastSkipNavigation(): void
    {
        $this->m->load();
        $this->assertSame(5, $this->m->loaded());
        $this->m->first();
        $first = $this->m->n;
        $this->m->last();
        $last = $this->m->n;
        $this->assertNotSame($first, $last);
    }

    public function testDryReportsEmptyCursor(): void
    {
        $fresh = new JigMapper($this->db, 'absent');
        $this->assertTrue($fresh->dry());
    }

    public function testFindoneReturnsFalseForNoMatch(): void
    {
        $result = $this->m->findone(['@n=?', 999]);
        $this->assertFalse($result);
    }

    public function testCountWithFilter(): void
    {
        // Records have n=1..5; those with n > 3 are n=4 and n=5.
        $count = $this->m->count(['@n>?', 3]);
        $this->assertSame(2, $count);
    }

    public function testNextAndPrevNavigate(): void
    {
        $this->m->load();
        $this->m->first();
        $firstVal = $this->m->n;
        $this->m->next();
        $afterNext = $this->m->n;
        $this->assertNotSame($firstVal, $afterNext);
        $this->m->prev();
        $this->assertSame($firstVal, $this->m->n);
    }

    public function testSkipAdvancesByOffset(): void
    {
        $this->m->load(null, ['order' => 'n']);
        // ptr is 0: first record (n=1). skip(1) moves to ptr=1 (n=2).
        $this->m->skip(1);
        $this->assertSame(2, $this->m->n);
    }
}
