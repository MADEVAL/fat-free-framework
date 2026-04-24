<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Base;
use Log;
use PHPUnit\Framework\TestCase;

/**
 * Log writes timestamped lines to LOGS directory.
 */
final class LogTest extends TestCase
{
    private string $logsDir;
    private string $file = 'unit-test.log';

    protected function setUp(): void
    {
        $f3 = Base::instance();
        $this->logsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3logs-' . uniqid() . DIRECTORY_SEPARATOR;
        if (!is_dir($this->logsDir)) {
            mkdir($this->logsDir, 0777, true);
        }
        $f3->set('LOGS', $this->logsDir);
    }

    protected function tearDown(): void
    {
        if (is_file($this->logsDir . $this->file)) {
            unlink($this->logsDir . $this->file);
        }
        @rmdir($this->logsDir);
    }

    public function testWriteCreatesFileWithMessage(): void
    {
        $log = new Log($this->file);
        $log->write('hello world');
        $contents = file_get_contents($this->logsDir . $this->file);
        $this->assertStringContainsString('hello world', $contents);
    }

    public function testWriteAppendsMultipleLines(): void
    {
        $log = new Log($this->file);
        $log->write('line one');
        $log->write('line two');
        $contents = file_get_contents($this->logsDir . $this->file);
        $this->assertStringContainsString('line one', $contents);
        $this->assertStringContainsString('line two', $contents);
    }

    public function testEraseRemovesFile(): void
    {
        $log = new Log($this->file);
        $log->write('toBeErased');
        $this->assertFileExists($this->logsDir . $this->file);
        $log->erase();
        $this->assertFileDoesNotExist($this->logsDir . $this->file);
    }

    public function testWriteMultilineTextProducesSeparateLogLines(): void
    {
        $log = new Log($this->file);
        $log->write("first line\nsecond line\nthird line");
        $contents = file_get_contents($this->logsDir . $this->file);
        $this->assertStringContainsString('first line', $contents);
        $this->assertStringContainsString('second line', $contents);
        $this->assertStringContainsString('third line', $contents);
        // Three separate lines written: at least 3 newlines.
        $this->assertGreaterThanOrEqual(3, substr_count($contents, PHP_EOL));
    }

    public function testWriteIncludesRemoteAddrWhenSet(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        try {
            $log = new Log($this->file);
            $log->write('addr test');
            $contents = file_get_contents($this->logsDir . $this->file);
            $this->assertStringContainsString('[1.2.3.4]', $contents);
        } finally {
            unset($_SERVER['REMOTE_ADDR']);
        }
    }

    public function testWriteWithCustomDateFormat(): void
    {
        $log = new Log($this->file);
        $log->write('custom format', 'Y');
        $contents = file_get_contents($this->logsDir . $this->file);
        // Custom format 'Y' writes the 4-digit year.
        $this->assertStringContainsString((string) date('Y'), $contents);
        $this->assertStringContainsString('custom format', $contents);
    }
}
