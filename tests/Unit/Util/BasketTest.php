<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Basket;
use PHPUnit\Framework\TestCase;

/**
 * Session-backed Basket. Each test isolates state via a unique session key
 * so the suite does not need a separate-process attribute.
 */
final class BasketTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_cookies', '0');
            ini_set('session.cache_limiter', '');
            session_id('test-' . uniqid());
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }

    public function testSetExistsClear(): void
    {
        $b = new Basket('cart-' . uniqid());
        $b->item = 'apple';
        $b->qty  = 3;
        $b->save();

        $this->assertTrue($b->exists('item'));
        $this->assertSame('apple', $b->item);
        $b->clear('item');
        $this->assertFalse($b->exists('item'));
    }

    public function testCountAndFind(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->item = 'apple'; $b->save();
        $b->reset(); $b->item = 'banana'; $b->save();
        $b->reset(); $b->item = 'cherry'; $b->save();

        $b2 = new Basket($key);
        $this->assertSame(3, $b2->count());
        $found = $b2->find('item', 'banana');
        $this->assertCount(1, $found);
    }

    public function testEraseRemovesEntry(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->item = 'rm-me'; $b->save();
        $this->assertSame(1, $b->count());
        $b->erase('item', 'rm-me');
        $this->assertSame(0, (new Basket($key))->count());
    }

    public function testDropClearsAll(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->x = 1; $b->save();
        $b->reset(); $b->x = 2; $b->save();
        $b->drop();
        $this->assertSame(0, (new Basket($key))->count());
    }

    public function testCheckoutReturnsAllAndClears(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->n = 1; $b->save();
        $b->reset(); $b->n = 2; $b->save();

        $rows = $b->checkout();
        $this->assertCount(2, $rows);
        $this->assertSame(0, (new Basket($key))->count());
    }

    public function testFindoneReturnsFirstMatchAndFalseOnMiss(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->item = 'mango'; $b->save();

        $found = $b->findone('item', 'mango');
        $this->assertInstanceOf(Basket::class, $found);
        $this->assertSame('mango', $found->item);

        $this->assertFalse($b->findone('item', 'nonexistent'));
    }

    public function testLoadPopulatesCursorAndReturnsEmptyOnMiss(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->item = 'kiwi'; $b->save();

        $result = $b->load('item', 'kiwi');
        $this->assertSame(['item' => 'kiwi'], $result);

        $miss = $b->load('item', 'nonesuch');
        $this->assertSame([], $miss);
    }

    public function testDryAfterResetAndAfterSet(): void
    {
        $b = new Basket('cart-' . uniqid());
        $this->assertTrue($b->dry());
        $b->item = 'plum';
        $this->assertFalse($b->dry());
        $b->reset();
        $this->assertTrue($b->dry());
    }

    public function testCopyfromArray(): void
    {
        $b = new Basket('cart-' . uniqid());
        $b->copyfrom(['item' => 'fig', 'qty' => 3]);
        $this->assertTrue($b->exists('item'));
        $this->assertSame('fig', $b->item);
        $this->assertSame(3, $b->qty);
    }

    public function testCopytoPopulatesHiveKey(): void
    {
        $f3 = \Base::instance();
        $b = new Basket('cart-' . uniqid());
        $b->item = 'lime';
        $b->qty  = 7;
        $b->copyto('BASKET_OUT');
        $this->assertSame('lime', $f3->get('BASKET_OUT.item'));
        $this->assertSame(7, $f3->get('BASKET_OUT.qty'));
        $f3->clear('BASKET_OUT');
    }

    public function testSetIdReturnsFalse(): void
    {
        $b = new Basket('cart-' . uniqid());
        $this->assertFalse($b->set('_id', 'anything'));
    }

    public function testGetIdReturnsCurrentId(): void
    {
        $b = new Basket('cart-' . uniqid());
        $b->item = 'peach';
        $b->save();
        $id = $b->get('_id');
        $this->assertNotEmpty($id);
    }

    public function testGetMissingFieldThrowsException(): void
    {
        $b = new Basket('cart-' . uniqid());
        $this->expectException(\Exception::class);
        $b->get('field_that_does_not_exist');
    }

    public function testFindWithNoArgsReturnsAllItems(): void
    {
        $key = 'cart-' . uniqid();
        $b = new Basket($key);
        $b->item = 'a'; $b->save();
        $b->reset(); $b->item = 'b'; $b->save();

        $all = $b->find();
        $this->assertCount(2, $all);
    }

    public function testCopyfromHiveStringReadsFromHive(): void
    {
        $f3 = \Base::instance();
        $f3->set('BSKT_SRC', ['item' => 'guava', 'qty' => 2]);
        $b = new Basket('cart-' . uniqid());
        $b->copyfrom('BSKT_SRC');
        $this->assertSame('guava', $b->item);
        $this->assertSame(2, $b->qty);
        $f3->clear('BSKT_SRC');
    }
}
