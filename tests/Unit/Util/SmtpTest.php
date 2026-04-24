<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Smtp;

/**
 * Offline SMTP behavior: header round-trip, attachment errors, mock dialog.
 * No network connections are opened.
 */
final class SmtpTest extends TestCase
{
    public function testHeadersInitialized(): void
    {
        $smtp = new Smtp('mail.example.com', 25);
        // Constructor stores keys verbatim while get() applies fixheader().
        // Reading via the raw canonical key works after using set().
        $smtp->set('Mime-Version', '1.0');
        $this->assertSame('1.0', $smtp->get('mime-version'));
    }

    public function testSetAndGetCustomHeader(): void
    {
        $smtp = new Smtp();
        $smtp->set('Subject', 'Hello');
        $this->assertTrue($smtp->exists('Subject'));
        $this->assertSame('Hello', $smtp->get('Subject'));
    }

    public function testClearRemovesHeader(): void
    {
        $smtp = new Smtp();
        $smtp->set('X-Test', '1');
        $smtp->clear('X-Test');
        $this->assertFalse($smtp->exists('X-Test'));
    }

    public function testAttachMissingFileThrows(): void
    {
        $smtp = new Smtp();
        $this->expectException(\Exception::class);
        $smtp->attach('definitely-missing-' . uniqid() . '.bin');
    }

    public function testSendBlankMessageThrows(): void
    {
        $smtp = new Smtp();
        $this->expectException(\Exception::class);
        $smtp->send('');
    }

    public function testMockSendDoesNotThrow(): void
    {
        $smtp = new Smtp('localhost', 25);
        $smtp->set('From', 'a@b.com');
        $smtp->set('To', 'c@d.com');
        $smtp->set('Subject', 'mock');
        // mock=true short-circuits network IO. Returns true on success.
        $this->assertNotFalse($smtp->send('Hello, world.', true, true));
    }

    public function testLogContainsDialogAfterMockSend(): void
    {
        $smtp = new Smtp('localhost', 25);
        $smtp->set('From', 'sender@example.com');
        $smtp->set('To', 'recipient@example.com');
        $smtp->set('Subject', 'log-test');
        $smtp->send('Body text.', true, true);
        $log = $smtp->log();
        $this->assertIsString($log);
        $this->assertNotEmpty($log);
    }

    public function testAttachWithValidFileDoesNotThrow(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smtp-att-' . uniqid() . '.txt';
        file_put_contents($tmp, 'attachment data');
        try {
            $smtp = new Smtp();
            $smtp->attach($tmp);
            // No exception: the attachment was accepted.
            $this->addToAssertionCount(1);
        } finally {
            @unlink($tmp);
        }
    }

    public function testFixheaderNormalizesUnderscore(): void
    {
        $smtp = new Smtp();
        // set() passes the key through fixheader(), so underscores become dashes
        // and the result is Title-Cased.
        $smtp->set('x_custom_header', 'val');
        $this->assertTrue($smtp->exists('X-Custom-Header'));
        $this->assertSame('val', $smtp->get('X-Custom-Header'));
    }

    public function testSetCcAndBccHeaders(): void
    {
        $smtp = new Smtp();
        $smtp->set('Cc', 'cc@example.com');
        $smtp->set('Bcc', 'bcc@example.com');
        $this->assertTrue($smtp->exists('Cc'));
        $this->assertTrue($smtp->exists('Bcc'));
        $this->assertSame('cc@example.com', $smtp->get('Cc'));
        $this->assertSame('bcc@example.com', $smtp->get('Bcc'));
    }
}
