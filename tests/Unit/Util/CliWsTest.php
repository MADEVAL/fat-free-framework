<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use CLI\WS;

/**
 * The WebSocket server requires sockets and an event loop; we only verify the
 * class is loadable, exposes the expected public surface, and constants are
 * defined as documented.
 */
final class CliWsTest extends TestCase
{
    public function testClassIsAvailable(): void
    {
        $this->assertTrue(class_exists(WS::class));
    }

    public function testExposesExpectedPublicMethods(): void
    {
        $rc = new ReflectionClass(WS::class);
        $names = array_map(fn ($m) => $m->getName(), $rc->getMethods(\ReflectionMethod::IS_PUBLIC));
        foreach (['agents', 'events', 'run'] as $expected) {
            $this->assertContains($expected, $names, "WS must expose $expected()");
        }
    }

    public function testHasOpcodeConstants(): void
    {
        $rc = new ReflectionClass(WS::class);
        $constants = $rc->getConstants();
        // WS protocol opcodes are typically defined as Opcode_* class constants.
        $opcodes = array_filter(
            array_keys($constants),
            fn ($k) => stripos($k, 'opcode') !== false
        );
        $this->assertNotEmpty($opcodes);
    }
}
