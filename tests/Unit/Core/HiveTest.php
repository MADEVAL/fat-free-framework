<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Hive operations: set/get/exists/clear/copy/concat/push/pop/shift/unshift/merge/extend/ref,
 * mset, status, hash, encode/decode, clean, split, fixslashes.
 */
final class HiveTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        // Pristine namespace per test.
        $this->f3->clear('TEST');
    }

    public function testSetGetScalar(): void
    {
        $this->f3->set('TEST.scalar', 42);
        $this->assertSame(42, $this->f3->get('TEST.scalar'));
    }

    public function testSetGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->f3->get('TEST.does.not.exist'));
    }

    public function testSetGetDeepPathCreatesIntermediateArrays(): void
    {
        $this->f3->set('TEST.a.b.c', 'leaf');
        $this->assertSame(['c' => 'leaf'], $this->f3->get('TEST.a.b'));
        $this->assertSame(['b' => ['c' => 'leaf']], $this->f3->get('TEST.a'));
    }

    public function testExistsReturnsFalseForNullValue(): void
    {
        // Base::exists() uses isset semantics: null is treated as missing.
        $this->f3->set('TEST.maybe', null);
        $this->assertFalse($this->f3->exists('TEST.maybe'));
        $this->assertFalse($this->f3->exists('TEST.never'));
    }

    public function testDevoidDistinguishesEmpty(): void
    {
        $this->f3->set('TEST.empty', '');
        $this->assertTrue($this->f3->devoid('TEST.empty'));
        $this->f3->set('TEST.full', 'x');
        $this->assertFalse($this->f3->devoid('TEST.full'));
    }

    public function testExistsByReferencePopulatesValue(): void
    {
        $this->f3->set('TEST.x', 'abc');
        $val = null;
        $ok = $this->f3->exists('TEST.x', $val);
        $this->assertTrue($ok);
        $this->assertSame('abc', $val);
    }

    public function testClearRemovesKey(): void
    {
        $this->f3->set('TEST.zap', 1);
        $this->assertTrue($this->f3->exists('TEST.zap'));
        $this->f3->clear('TEST.zap');
        $this->assertFalse($this->f3->exists('TEST.zap'));
    }

    public function testClearOnEntireBranchRemovesAllChildren(): void
    {
        $this->f3->set('TEST.a.b', 1);
        $this->f3->set('TEST.a.c', 2);
        $this->f3->clear('TEST.a');
        $this->assertFalse($this->f3->exists('TEST.a.b'));
        $this->assertFalse($this->f3->exists('TEST.a.c'));
    }

    public function testCopyDuplicatesValue(): void
    {
        $this->f3->set('TEST.src', ['x' => 1, 'y' => 2]);
        $this->f3->copy('TEST.src', 'TEST.dst');
        $this->assertSame(['x' => 1, 'y' => 2], $this->f3->get('TEST.dst'));
    }

    public function testConcatAppendsToString(): void
    {
        $this->f3->set('TEST.s', 'foo');
        $this->f3->concat('TEST.s', 'bar');
        $this->assertSame('foobar', $this->f3->get('TEST.s'));
    }

    public function testPushAppendsToArray(): void
    {
        $this->f3->set('TEST.list', [1, 2]);
        $this->f3->push('TEST.list', 3);
        $this->assertSame([1, 2, 3], $this->f3->get('TEST.list'));
    }

    public function testPopRemovesAndReturnsLast(): void
    {
        $this->f3->set('TEST.list', [1, 2, 3]);
        $popped = $this->f3->pop('TEST.list');
        $this->assertSame(3, $popped);
        $this->assertSame([1, 2], $this->f3->get('TEST.list'));
    }

    public function testUnshiftPrepends(): void
    {
        $this->f3->set('TEST.list', [2, 3]);
        $this->f3->unshift('TEST.list', 1);
        $this->assertSame([1, 2, 3], $this->f3->get('TEST.list'));
    }

    public function testShiftRemovesFirst(): void
    {
        $this->f3->set('TEST.list', [1, 2, 3]);
        $shifted = $this->f3->shift('TEST.list');
        $this->assertSame(1, $shifted);
        $this->assertSame([2, 3], $this->f3->get('TEST.list'));
    }

    public function testMergeArraysCombinesNumericKeys(): void
    {
        $this->f3->set('TEST.list', [1, 2]);
        $merged = $this->f3->merge('TEST.list', [3, 4]);
        $this->assertSame([1, 2, 3, 4], $merged);
    }

    public function testExtendKeepsExistingWhenFlagged(): void
    {
        $this->f3->set('TEST.cfg', ['a' => 1, 'b' => 2]);
        $this->f3->extend('TEST.cfg', ['b' => 99, 'c' => 3], true);
        $cfg = $this->f3->get('TEST.cfg');
        $this->assertSame(2, $cfg['b'], 'keep=true must NOT overwrite existing');
        $this->assertSame(3, $cfg['c']);
    }

    public function testExtendFillsMissingKeysButPreservesExisting(): void
    {
        // Semantics: extend(key, src) treats hive value as authoritative and
        // src as defaults. Existing scalars are NEVER overwritten by extend.
        $this->f3->set('TEST.cfg', ['a' => 1]);
        $merged = $this->f3->extend('TEST.cfg', ['a' => 2, 'b' => 9], true);
        $this->assertSame(1, $merged['a']);
        $this->assertSame(9, $merged['b']);
        $this->assertSame(['a' => 1, 'b' => 9], $this->f3->get('TEST.cfg'));
    }

    public function testExtendDoesNotPersistByDefault(): void
    {
        $this->f3->set('TEST.cfg', ['a' => 1]);
        $this->f3->extend('TEST.cfg', ['b' => 9]);
        $this->assertSame(['a' => 1], $this->f3->get('TEST.cfg'));
    }

    public function testRefReturnsByReference(): void
    {
        $this->f3->set('TEST.list', []);
        $ref = &$this->f3->ref('TEST.list');
        $ref[] = 'pushed-via-ref';
        $this->assertSame(['pushed-via-ref'], $this->f3->get('TEST.list'));
    }

    public function testRefNoCreateReturnsNullForMissing(): void
    {
        $val = $this->f3->ref('TEST.missing.path', false);
        $this->assertNull($val);
        $this->assertFalse($this->f3->exists('TEST.missing'));
    }

    public function testArrayAccessInterfaceWorks(): void
    {
        $this->f3['TEST.via.array'] = 'works';
        $this->assertTrue(isset($this->f3['TEST.via.array']));
        $this->assertSame('works', $this->f3['TEST.via.array']);
        unset($this->f3['TEST.via.array']);
        $this->assertFalse(isset($this->f3['TEST.via.array']));
    }

    public function testMagicPropertyAccess(): void
    {
        $this->f3->TEST = ['hello' => 'world'];
        $this->assertSame('world', $this->f3->get('TEST.hello'));
        unset($this->f3->TEST);
        $this->assertNull($this->f3->get('TEST'));
    }

    public function testSetWithTtlIntegerDoesNotThrow(): void
    {
        $this->f3->set('TEST.ttl', 'x', 60);
        $this->assertSame('x', $this->f3->get('TEST.ttl'));
    }

    public function testSyncBindsGlobal(): void
    {
        $_GET['probe'] = 'value';
        $this->f3->sync('GET');
        $this->assertSame('value', $this->f3->get('GET.probe'));
        unset($_GET['probe']);
    }

    public function testDevoidReturnsTrueForCompletelyMissingKey(): void
    {
        // A key that was never set must be devoid.
        $this->assertFalse($this->f3->exists('TEST.definitely_never_set'));
        $this->assertTrue($this->f3->devoid('TEST.definitely_never_set'));
    }

    public function testMergeAssocKeyOverwrite(): void
    {
        $this->f3->set('TEST.base', ['a' => 1, 'b' => 2]);
        $out = $this->f3->merge('TEST.base', ['b' => 99, 'c' => 3]);
        // array_merge: string keys from the second array overwrite those from the first.
        $this->assertSame(99, $out['b']);
        $this->assertSame(3, $out['c']);
        $this->assertSame(1, $out['a']);
    }

    // -- additional hive helpers: mset, status, hash, encode/decode, clean --

    public function testMsetMassAssign(): void
    {
        $this->f3->mset(['a' => 1, 'b' => 2], 'mset.');
        $this->assertSame(1, $this->f3->get('mset.a'));
        $this->assertSame(2, $this->f3->get('mset.b'));
    }

    public function testStatusSetsResponseCode(): void
    {
        $msg = $this->f3->status(404);
        $this->assertStringContainsString('Not Found', $msg);
    }

    public function testHashIsDeterministic(): void
    {
        $a = $this->f3->hash('input');
        $b = $this->f3->hash('input');
        $c = $this->f3->hash('input2');
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $raw = '<a href="x">"&\'</a>';
        $enc = $this->f3->encode($raw);
        $this->assertSame($raw, $this->f3->decode($enc));
    }

    public function testCleanRemovesNullBytes(): void
    {
        $clean = $this->f3->clean("a\x00b");
        $this->assertSame('ab', $clean);
    }

    public function testSplitProducesArray(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->f3->split('a,b;c|'));
    }

    public function testFixslashesNormalizes(): void
    {
        $this->assertSame('a/b/c', $this->f3->fixslashes('a\\b\\c'));
    }

    public function testConcatAppends(): void
    {
        $this->f3->set('s', 'hi');
        $this->f3->concat('s', '-end');
        $this->assertSame('hi-end', $this->f3->get('s'));
    }
}
