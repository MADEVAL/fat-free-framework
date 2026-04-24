<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Matrix;
use PHPUnit\Framework\TestCase;

/**
 * Two-dimensional array helpers: pick, select, walk, transpose, sort,
 * changekey, calendar.
 */
final class MatrixTest extends TestCase
{
    private Matrix $matrix;

    protected function setUp(): void
    {
        $this->matrix = Matrix::instance();
    }

    public function testPickColumn(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
            ['id' => 3, 'name' => 'c'],
        ];
        $this->assertSame([1, 2, 3], $this->matrix->pick($rows, 'id'));
        $this->assertSame(['a', 'b', 'c'], $this->matrix->pick($rows, 'name'));
    }

    public function testSelectSubsetOfFields(): void
    {
        // select() filters keys of a single associative array.
        $row = ['id' => 1, 'name' => 'a', 'age' => 10];
        $out = $this->matrix->select(['id', 'name'], $row);
        $this->assertSame(['id' => 1, 'name' => 'a'], $out);
        $this->assertArrayNotHasKey('age', $out);
    }

    public function testSelectAcceptsSplittableString(): void
    {
        $row = ['id' => 1, 'name' => 'a', 'age' => 10];
        $out = $this->matrix->select('id|name', $row);
        $this->assertSame(['id', 'name'], array_keys($out));
    }

    public function testTransposeFlipsAxes(): void
    {
        $grid = [
            'r1' => ['a' => 1, 'b' => 2],
            'r2' => ['a' => 3, 'b' => 4],
        ];
        $this->matrix->transpose($grid);
        // After transpose, second-level keys become top keys.
        $this->assertArrayHasKey('a', $grid);
        $this->assertArrayHasKey('b', $grid);
        $this->assertSame(1, $grid['a']['r1']);
        $this->assertSame(4, $grid['b']['r2']);
    }

    public function testPickColumnReturnsFlatList(): void
    {
        $rows = [
            ['x' => 'a'], ['x' => 'b'], ['x' => 'c'],
        ];
        $this->assertSame(['a', 'b', 'c'], array_values($this->matrix->pick($rows, 'x')));
    }

    public function testSortByColumnAsc(): void
    {
        $rows = [
            ['n' => 3], ['n' => 1], ['n' => 2],
        ];
        $this->matrix->sort($rows, 'n', SORT_ASC);
        $this->assertSame([1, 2, 3], array_column($rows, 'n'));
    }

    public function testSortByColumnDesc(): void
    {
        $rows = [
            ['n' => 1], ['n' => 3], ['n' => 2],
        ];
        $this->matrix->sort($rows, 'n', SORT_DESC);
        $this->assertSame([3, 2, 1], array_column($rows, 'n'));
    }

    public function testChangekey(): void
    {
        // changekey renames the named key on a single-level array (not on each
        // row of a 2D array as the docblock suggests).
        $row = ['id' => 1, 'old' => 'x'];
        $this->matrix->changekey($row, 'old', 'new');
        $this->assertArrayHasKey('new', $row);
        $this->assertArrayNotHasKey('old', $row);
        $this->assertSame('x', $row['new']);
    }

    public function testCalendarReturnsWeeks(): void
    {
        if (!extension_loaded('calendar')) {
            $this->markTestSkipped('ext-calendar not loaded');
        }
        $cal = $this->matrix->calendar('2024-07-04', 0);
        $this->assertIsArray($cal);
        $this->assertNotEmpty($cal);
        foreach ($cal as $week) {
            $this->assertIsArray($week);
            $this->assertLessThanOrEqual(7, count($week));
        }
    }

    public function testWalkAppliesCallbackToSelectedFields(): void
    {
        $row = ['a' => 'x', 'b' => 1];
        $out = $this->matrix->walk(['a'], $row, function (&$v, $k) {
            $v = strtoupper((string) $v);
        });
        $this->assertSame('X', $out['a']);
        // walk() returns the SUBSET only: fields outside the selection are absent.
        $this->assertArrayNotHasKey('b', $out);
    }

    public function testSortByStringValueAscending(): void
    {
        $rows = [
            ['s' => 'cherry'],
            ['s' => 'apple'],
            ['s' => 'banana'],
        ];
        $this->matrix->sort($rows, 's', SORT_ASC);
        $this->assertSame(['apple', 'banana', 'cherry'], array_column($rows, 's'));
    }

    public function testSortByStringValueDescending(): void
    {
        $rows = [
            ['s' => 'apple'],
            ['s' => 'cherry'],
            ['s' => 'banana'],
        ];
        $this->matrix->sort($rows, 's', SORT_DESC);
        $this->assertSame(['cherry', 'banana', 'apple'], array_column($rows, 's'));
    }

    public function testWalkPassesFullInputArrayToCallback(): void
    {
        $row      = ['a' => 10, 'b' => 20, 'c' => 30];
        $captured = null;
        $this->matrix->walk(['a'], $row, function (&$v, $k, $full) use (&$captured) {
            $captured = $full;
        });
        $this->assertSame($row, $captured);
    }

    public function testSelectReadsFromHiveWhenStringPassed(): void
    {
        $f3 = \Base::instance();
        $f3->set('MTX_DATA', ['id' => 7, 'name' => 'x', 'age' => 5]);
        $out = $this->matrix->select(['id', 'name'], 'MTX_DATA');
        $this->assertSame(['id' => 7, 'name' => 'x'], $out);
        $f3->clear('MTX_DATA');
    }

    public function testCalendarMondayFirst(): void
    {
        if (!extension_loaded('calendar')) {
            $this->markTestSkipped('ext-calendar not loaded');
        }
        $cal = $this->matrix->calendar('2024-07-01', 1);
        $this->assertIsArray($cal);
        $this->assertNotEmpty($cal);
        foreach ($cal as $week) {
            $this->assertLessThanOrEqual(7, count($week));
        }
    }
}
