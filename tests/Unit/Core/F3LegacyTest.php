<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use F3;
use PHPUnit\Framework\TestCase;

final class F3LegacyTest extends TestCase
{
    public function testStaticForwardsToBase(): void
    {
        F3::set('legacy.x', 'val');
        $this->assertSame('val', F3::get('legacy.x'));
        F3::clear('legacy.x');
        $this->assertFalse(F3::exists('legacy.x'));
    }

    public function testStaticConcat(): void
    {
        F3::set('legacy.s', 'a');
        F3::concat('legacy.s', 'bc');
        $this->assertSame('abc', F3::get('legacy.s'));
        F3::clear('legacy.s');
    }
}
