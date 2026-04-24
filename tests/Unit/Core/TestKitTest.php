<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Test;

final class TestKitTest extends TestCase
{
    public function testExpectRecordsResultAndPassedFlag(): void
    {
        $t = new Test();
        $t->expect(true, 'first');
        $t->expect(false, 'second');
        $r = $t->results();
        $this->assertCount(2, $r);
        $this->assertTrue($r[0]['status']);
        $this->assertFalse($r[1]['status']);
        $this->assertFalse($t->passed());
        $this->assertStringContainsString(str_replace('\\', '/', __FILE__), $r[0]['source']);
    }

    public function testMessageAppendsPositiveResult(): void
    {
        $t = new Test();
        $t->message('note');
        $this->assertCount(1, $t->results());
        $this->assertTrue($t->passed());
    }

    public function testFlagFalseRecordsOnlyFailures(): void
    {
        $t = new Test(Test::FLAG_False);
        $t->expect(true);
        $t->expect(false);
        $this->assertCount(1, $t->results());
    }
}
