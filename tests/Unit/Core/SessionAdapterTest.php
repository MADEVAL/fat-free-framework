<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SessionAdapter;

final class SessionAdapterTest extends TestCase
{
    public function testForwardsAllHandlerCalls(): void
    {
        $stub = new class {
            public array $log = [];
            public function close() { $this->log[] = 'close'; return true; }
            public function destroy($id) { $this->log[] = "destroy:$id"; return true; }
            public function gc($l) { $this->log[] = "gc:$l"; return 7; }
            public function open($p, $n) { $this->log[] = "open:$p:$n"; return true; }
            public function read($id) { $this->log[] = "read:$id"; return 'data'; }
            public function write($id, $d) { $this->log[] = "write:$id:$d"; return true; }
        };

        $a = new SessionAdapter($stub);
        $this->assertTrue($a->open('p', 'n'));
        $this->assertSame('data', $a->read('id1'));
        $this->assertTrue($a->write('id1', 'x'));
        $this->assertSame(7, $a->gc(120));
        $this->assertTrue($a->destroy('id1'));
        $this->assertTrue($a->close());

        $this->assertSame(
            ['open:p:n', 'read:id1', 'write:id1:x', 'gc:120', 'destroy:id1', 'close'],
            $stub->log
        );
    }
}
