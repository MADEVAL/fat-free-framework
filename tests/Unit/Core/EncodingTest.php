<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Encoding, escaping, hashing and recursive sanitisation primitives.
 * These power XSS protection across views and request-input handling.
 */
final class EncodingTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
    }

    public function testHashIsBase36MinEleven(): void
    {
        $h = $this->f3->hash('hello-world');
        $this->assertMatchesRegularExpression('/^[0-9a-z]{11,13}$/', $h);
    }

    public function testHashIsDeterministic(): void
    {
        $this->assertSame($this->f3->hash('abc'), $this->f3->hash('abc'));
    }

    public function testHashEmptyStringDoesNotThrow(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-z]{11,13}$/', $this->f3->hash(''));
    }

    public function testEncodeEscapesHtmlSpecialChars(): void
    {
        $out = $this->f3->encode('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testEncodeDoubleQuotesEscaped(): void
    {
        // Default BITMASK is ENT_COMPAT: double quotes escaped, single quotes left alone.
        $out = $this->f3->encode('"hi"');
        $this->assertStringContainsString('&quot;', $out);
    }

    public function testEncodeSingleQuotesEscapedWhenBitmaskIncludesQuotes(): void
    {
        $orig = $this->f3->get('BITMASK');
        $this->f3->set('BITMASK', ENT_QUOTES);
        try {
            $out = $this->f3->encode("it's");
            $this->assertStringContainsString('&#039;', $out);
        } finally {
            $this->f3->set('BITMASK', $orig);
        }
    }

    public function testDecodeReversesEncode(): void
    {
        $original = '<b>"x"</b>';
        $this->assertSame($original, $this->f3->decode($this->f3->encode($original)));
    }

    public function testScrubStripsAllTagsByDefault(): void
    {
        $val = '<p>hi <script>bad()</script></p>';
        $this->f3->scrub($val);
        $this->assertSame('hi bad()', $val);
    }

    public function testScrubKeepsAllowedTags(): void
    {
        $val = '<p>x<script>y</script></p>';
        $this->f3->scrub($val, 'p');
        $this->assertStringContainsString('<p>', $val);
        $this->assertStringNotContainsString('<script>', $val);
    }

    public function testScrubStripsControlChars(): void
    {
        $val = "abc\x01\x02def";
        $this->f3->scrub($val);
        $this->assertSame('abcdef', $val);
    }

    public function testRecursiveAppliesCallbackToLeavesOnly(): void
    {
        $tree = ['a' => ' x ', 'b' => [' y ', ' z ']];
        $out = $this->f3->recursive($tree, fn($v) => trim($v));
        $this->assertSame(['a' => 'x', 'b' => ['y', 'z']], $out);
    }

    public function testRecursiveHandlesObjects(): void
    {
        $obj = (object) ['name' => '  fred  '];
        $out = $this->f3->recursive($obj, fn($v) => trim($v));
        $this->assertSame('fred', $out->name);
    }

    public function testCleanWildcardKeepsAllTags(): void
    {
        $val = '<a><b>x</b></a>';
        $this->assertSame($val, $this->f3->clean($val, '*'));
    }

    public function testBase64BuildsDataUri(): void
    {
        $out = $this->f3->base64('payload', 'text/plain');
        $this->assertSame('data:text/plain;base64,'.base64_encode('payload'), $out);
    }
}
