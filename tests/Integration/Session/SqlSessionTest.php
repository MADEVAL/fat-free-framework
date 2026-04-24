<?php

declare(strict_types=1);

namespace Tests\Integration\Session;

use Base;
use DB\SQL;
use DB\SQL\Session as SqlSession;
use PHPUnit\Framework\TestCase;

/**
 * DB\SQL\Session lifecycle backed by SQLite in-memory.
 */
final class SqlSessionTest extends TestCase
{
    private SQL $db;
    private SqlSession $session;

    protected function setUp(): void
    {
        Base::instance();
        $this->db = new SQL('sqlite::memory:');
        $this->session = new SqlSession($this->db, 'sessions');
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $this->assertTrue($this->session->open('sql', 'sessions'));
        $sid = 'sid-' . uniqid();
        $payload = 'a|s:1:"x";';
        $this->assertTrue($this->session->write($sid, $payload));
        $this->assertSame($payload, $this->session->read($sid));
    }

    public function testDestroyRemovesSession(): void
    {
        $this->session->open('sql', 'sessions');
        $sid = 'sid-del';
        $this->session->write($sid, 'data');
        $this->assertTrue($this->session->destroy($sid));
        $this->assertSame('', $this->session->read($sid));
    }

    public function testGcReturnsInt(): void
    {
        $this->session->open('sql', 'sessions');
        $this->session->write('keep', 'val');
        $this->assertIsInt($this->session->gc(3600));
    }

    public function testSidReturnsIdAfterRead(): void
    {
        $this->session->open('sql', 'sessions');
        $sid = 'sid-accessor-' . uniqid();
        $this->session->write($sid, 'payload');
        $this->session->read($sid);
        $this->assertSame($sid, $this->session->sid());
    }

    public function testIpReturnsString(): void
    {
        $this->assertIsString($this->session->ip());
    }

    public function testAgentReturnsString(): void
    {
        $this->assertIsString($this->session->agent());
    }

    public function testCsrfReturnsNonEmptyString(): void
    {
        $csrf = $this->session->csrf();
        $this->assertIsString($csrf);
        $this->assertNotEmpty($csrf);
    }

    public function testStampReturnsFalseWhenNotLoaded(): void
    {
        // No write has been performed for this session id, so stamp() returns FALSE.
        $this->session->open('sql', 'sessions');
        $this->session->read('sid-no-stamp-' . uniqid());
        $this->assertFalse($this->session->stamp());
    }

    public function testStampReturnsIntAfterWrite(): void
    {
        $this->session->open('sql', 'sessions');
        $sid = 'sid-stamp-' . uniqid();
        $this->session->write($sid, 'data');
        $this->session->read($sid);
        $stamp = $this->session->stamp();
        $this->assertIsInt($stamp);
        $this->assertGreaterThan(0, $stamp);
    }

    public function testCloseResetsState(): void
    {
        $this->session->open('sql', 'sessions');
        $sid = 'sid-close-' . uniqid();
        $this->session->write($sid, 'data');
        $this->session->read($sid);
        $this->session->close();
        $this->assertNull($this->session->sid());
    }
}
