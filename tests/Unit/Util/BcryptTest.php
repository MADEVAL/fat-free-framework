<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Bcrypt;
use PHPUnit\Framework\TestCase;

/**
 * Custom Bcrypt wrapper. Validates hash format, verify behavior and
 * rejection of malformed cost/salt arguments.
 */
final class BcryptTest extends TestCase
{
    private Bcrypt $bc;

    protected function setUp(): void
    {
        $this->bc = Bcrypt::instance();
    }

    public function testHashProducesValidBcryptString(): void
    {
        $h = $this->bc->hash('correct horse battery staple', null, 4);
        $this->assertNotFalse($h);
        $this->assertMatchesRegularExpression('/^\$2y\$04\$/', $h);
        $this->assertGreaterThanOrEqual(60, strlen($h));
    }

    public function testHashIsNonDeterministic(): void
    {
        $a = $this->bc->hash('pw', null, 4);
        $b = $this->bc->hash('pw', null, 4);
        $this->assertNotSame($a, $b);
    }

    public function testVerifyAcceptsCorrectPassword(): void
    {
        $h = $this->bc->hash('secret', null, 4);
        $this->assertTrue($this->bc->verify('secret', $h));
    }

    public function testVerifyRejectsWrongPassword(): void
    {
        $h = $this->bc->hash('secret', null, 4);
        $this->assertFalse($this->bc->verify('wrong', $h));
    }

    public function testNeedsRehashWhenCostIncreased(): void
    {
        $h = $this->bc->hash('pw', null, 4);
        $this->assertTrue($this->bc->needs_rehash($h, 6));
        $this->assertFalse($this->bc->needs_rehash($h, 4));
    }

    public function testInvalidCostThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->bc->hash('pw', null, 99);
    }

    public function testInvalidSaltThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->bc->hash('pw', 'short!');
    }

    public function testCustomValidSalt(): void
    {
        $salt = 'abcdefghijklmnopqrstuv'; // 22 chars alnum.
        $h = $this->bc->hash('pw', $salt, 4);
        $this->assertNotFalse($h);
        $this->assertTrue($this->bc->verify('pw', $h));
    }
}
