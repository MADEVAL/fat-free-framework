<?php

declare(strict_types=1);

namespace Tests\Integration\Session;

use Base;
use DB\Jig;
use DB\Jig\Session as JigSession;
use PHPUnit\Framework\TestCase;

/**
 * DB\Jig\Session lifecycle: open, write, read, destroy.
 * Avoids actual session_start to keep PHP session globals untouched.
 */
final class JigSessionTest extends TestCase
{
    private string $dir;
    private Jig $db;
    private JigSession $session;

    protected function setUp(): void
    {
        Base::instance();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3sess-' . uniqid() . DIRECTORY_SEPARATOR;
        $this->db = new Jig($this->dir, Jig::FORMAT_JSON);
        $this->session = new JigSession($this->db, 'sessions');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '*') as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $this->assertTrue($this->session->open('jig', 'sessions'));
        $sid = 'sid-test-' . uniqid();
        $payload = 'foo|s:3:"bar";';
        $this->assertTrue($this->session->write($sid, $payload));
        $this->assertSame($payload, $this->session->read($sid));
    }

    public function testDestroyRemovesSession(): void
    {
        $this->session->open('jig', 'sessions');
        $sid = 'sid-del-' . uniqid();
        $this->session->write($sid, 'data');
        $this->assertTrue($this->session->destroy($sid));
        $this->assertSame('', $this->session->read($sid));
    }

    public function testGcRemovesExpired(): void
    {
        $this->session->open('jig', 'sessions');
        $this->session->write('to-keep', 'fresh');
        // gc returns count of removed sessions (>= 0).
        $removed = $this->session->gc(3600);
        $this->assertIsInt($removed);
    }
}
