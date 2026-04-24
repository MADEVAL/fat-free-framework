<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Base::call / chain / relay / grab — F3 dynamic invocation surface.
 */
final class CallChainTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
    }

    public function testCallClosure(): void
    {
        $out = $this->f3->call(fn ($a, $b) => $a + $b, [2, 3]);
        $this->assertSame(5, $out);
    }

    public function testCallStringFunction(): void
    {
        $this->assertSame('HELLO', $this->f3->call('strtoupper', ['hello']));
    }

    public function testGrabSplitsClassMethod(): void
    {
        $obj = $this->f3->grab(GrabHelper::class . '->ping');
        $this->assertIsArray($obj);
        $this->assertInstanceOf(GrabHelper::class, $obj[0]);
        $this->assertSame('ping', $obj[1]);
    }

    public function testGrabStaticReturnsString(): void
    {
        $out = $this->f3->grab('strlen');
        $this->assertSame('strlen', $out);
    }

    public function testChainExecutesAllInOrder(): void
    {
        // chain takes pipe-delimited handler list; result is array of returns.
        $out = $this->f3->chain('strtoupper|strrev', 'abc');
        $this->assertSame(['ABC', 'cba'], $out);
    }

    public function testRelayFeedsResultForward(): void
    {
        // relay pipes the output of each handler into the next.
        $out = $this->f3->relay('strtoupper|strrev', 'abc');
        $this->assertSame('CBA', $out);
    }

    public function testGrabStaticMethodSyntaxBuildsCallable(): void
    {
        $func = $this->f3->grab(GrabStaticHelper::class . '::compute');
        $this->assertIsArray($func);
        $this->assertSame(GrabStaticHelper::class, $func[0]);
        $this->assertSame('compute', $func[1]);
    }

    public function testCallInvokesBeforeroureHook(): void
    {
        $handler = new HookedHandler();
        $out = $this->f3->call([$handler, 'run'], null, 'beforeroute,afterroute');
        $this->assertSame('body', $out);
        $this->assertTrue($handler->beforeCalled);
    }

    public function testCallInvokesAfterroureHook(): void
    {
        $handler = new HookedHandler();
        $this->f3->call([$handler, 'run'], null, 'beforeroute,afterroute');
        $this->assertTrue($handler->afterCalled);
    }

    public function testCallReturnsFalseWhenBeforeroureReturnsFalse(): void
    {
        $handler = new AbortingBeforeHandler();
        $out = $this->f3->call([$handler, 'run'], null, 'beforeroute,afterroute');
        $this->assertFalse($out);
        $this->assertFalse($handler->bodyCalled);
    }

    public function helper(): string
    {
        return 'hi';
    }
}

final class GrabHelper
{
    public function ping(): string
    {
        return 'pong';
    }
}

final class GrabStaticHelper
{
    public static function compute(): int
    {
        return 42;
    }
}

final class HookedHandler
{
    public bool $beforeCalled = false;
    public bool $afterCalled  = false;

    public function beforeroute(): void
    {
        $this->beforeCalled = true;
    }

    public function run(): string
    {
        return 'body';
    }

    public function afterroute(): void
    {
        $this->afterCalled = true;
    }
}

final class AbortingBeforeHandler
{
    public bool $bodyCalled = false;

    public function beforeroute(): bool
    {
        return false;
    }

    public function run(): string
    {
        $this->bodyCalled = true;
        return 'body';
    }
}
