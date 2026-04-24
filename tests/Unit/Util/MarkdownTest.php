<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Markdown;
use PHPUnit\Framework\TestCase;

/**
 * Markdown to HTML conversion: core constructs, setext headings, escapes,
 * fenced code with language hints (including HIGHLIGHT=true branches),
 * nested emphasis, inline HTML, reference links.
 */
final class MarkdownTest extends TestCase
{
    private Markdown $md;

    protected function setUp(): void
    {
        $this->md = Markdown::instance();
    }

    public function testAtxHeading(): void
    {
        $out = $this->md->convert('# Title');
        $this->assertMatchesRegularExpression('/<h1[^>]*>Title<\/h1>/', $out);
    }

    public function testAtxHeadingLevel3(): void
    {
        $out = $this->md->convert('### Sub');
        $this->assertMatchesRegularExpression('/<h3[^>]*>Sub<\/h3>/', $out);
    }

    public function testParagraph(): void
    {
        $out = $this->md->convert('plain text');
        $this->assertStringContainsString('<p>plain text</p>', $out);
    }

    public function testBoldEmphasis(): void
    {
        $out = $this->md->convert('**bold** and *italic*');
        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringContainsString('<em>italic</em>', $out);
    }

    public function testInlineCode(): void
    {
        $out = $this->md->convert('Use `foo()` now.');
        $this->assertStringContainsString('<code>foo()</code>', $out);
    }

    public function testFencedCodeBlock(): void
    {
        $src = "```\nline1\nline2\n```";
        $out = $this->md->convert($src);
        $this->assertStringContainsString('<pre>', $out);
        $this->assertStringContainsString('line1', $out);
    }

    public function testUnorderedList(): void
    {
        $src = "- a\n- b\n- c";
        $out = $this->md->convert($src);
        $this->assertStringContainsString('<ul>', $out);
        $this->assertStringContainsString('<li>a</li>', $out);
        $this->assertStringContainsString('<li>c</li>', $out);
    }

    public function testOrderedList(): void
    {
        $src = "1. one\n2. two";
        $out = $this->md->convert($src);
        $this->assertStringContainsString('<ol>', $out);
        $this->assertStringContainsString('<li>one</li>', $out);
    }

    public function testBlockquote(): void
    {
        $out = $this->md->convert('> quoted');
        $this->assertStringContainsString('<blockquote>', $out);
        $this->assertStringContainsString('quoted', $out);
    }

    public function testHorizontalRule(): void
    {
        $out = $this->md->convert("---");
        $this->assertStringContainsString('<hr', $out);
    }

    public function testLink(): void
    {
        $out = $this->md->convert('[text](https://example.com)');
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('>text</a>', $out);
    }

    public function testImage(): void
    {
        $out = $this->md->convert('![alt](pic.png)');
        $this->assertStringContainsString('<img', $out);
        $this->assertStringContainsString('src="pic.png"', $out);
        $this->assertStringContainsString('alt="alt"', $out);
    }

    // -- setext headings, escapes, nested emphasis, reference links ----------

    public function testSetextHeadingH1(): void
    {
        $out = $this->md->convert("Hello\n=====");
        $this->assertMatchesRegularExpression('/<h1[^>]*>Hello<\/h1>/', $out);
    }

    public function testSetextHeadingH2(): void
    {
        $out = $this->md->convert("Hello\n-----");
        $this->assertMatchesRegularExpression('/<h2[^>]*>Hello<\/h2>/', $out);
    }

    public function testMultipleParagraphs(): void
    {
        $out = $this->md->convert("Para one.\n\nPara two.");
        $this->assertMatchesRegularExpression(
            '/<p>Para one\.<\/p>\s*<p>Para two\.<\/p>/',
            $out
        );
    }

    public function testEscapedCharacter(): void
    {
        $out = $this->md->convert('not \*bold\*');
        $this->assertStringContainsString('*bold*', $out);
        $this->assertStringNotContainsString('<strong>', $out);
    }

    public function testFencedCodeWithLanguageHint(): void
    {
        $out = $this->md->convert("```php\n<?php echo 1;\n```");
        $this->assertStringContainsString('<pre>', $out);
        $this->assertStringContainsString('echo 1', $out);
    }

    public function testStrongEmCombined(): void
    {
        $out = $this->md->convert('***triple***');
        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('<em>', $out);
    }

    public function testInlineHtmlIsPreserved(): void
    {
        $out = $this->md->convert('<div class="x">raw</div>');
        $this->assertStringContainsString('<div class="x">', $out);
    }

    public function testReferenceStyleLink(): void
    {
        $src = "See [docs][1].\n\n[1]: https://example.com/doc";
        $out = $this->md->convert($src);
        $this->assertStringContainsString('href="https://example.com/doc"', $out);
    }

    // -- syntax highlighting via HIGHLIGHT flag ------------------------------

    public function testFencedCodePhpHintWithHighlightEnabled(): void
    {
        $f3 = \Base::instance();
        $f3->set('HIGHLIGHT', true);
        try {
            $out = $this->md->convert("```php\n<?php echo 1;\n```");
            $this->assertStringContainsString('<pre>', $out);
            $this->assertStringContainsString('<code>', $out);
        } finally {
            $f3->set('HIGHLIGHT', false);
        }
    }

    public function testFencedCodeHtmlHintWithHighlightEnabled(): void
    {
        $f3 = \Base::instance();
        $f3->set('HIGHLIGHT', true);
        try {
            $out = $this->md->convert("```html\n<div>text</div>\n```");
            $this->assertStringContainsString('<pre>', $out);
            $this->assertStringContainsString('xml_tag', $out);
        } finally {
            $f3->set('HIGHLIGHT', false);
        }
    }

    public function testFencedCodeIniHintWithHighlightEnabled(): void
    {
        $f3 = \Base::instance();
        $f3->set('HIGHLIGHT', true);
        try {
            $out = $this->md->convert("```ini\n[section]\nkey=value\n```");
            $this->assertStringContainsString('<pre>', $out);
            $this->assertStringContainsString('ini_section', $out);
        } finally {
            $f3->set('HIGHLIGHT', false);
        }
    }

    public function testFencedCodeApacheHintWithHighlightEnabled(): void
    {
        $f3 = \Base::instance();
        $f3->set('HIGHLIGHT', true);
        try {
            $out = $this->md->convert("```apache\nServerName example.com\n```");
            $this->assertStringContainsString('<pre>', $out);
            $this->assertStringContainsString('directive', $out);
        } finally {
            $f3->set('HIGHLIGHT', false);
        }
    }

    public function testFencedCodeUnknownHintWithHighlightEnabled(): void
    {
        $f3 = \Base::instance();
        $f3->set('HIGHLIGHT', true);
        try {
            $out = $this->md->convert("```unknown-lang\nsome code\n```");
            $this->assertStringContainsString('<pre>', $out);
            $this->assertStringContainsString('<code>', $out);
        } finally {
            $f3->set('HIGHLIGHT', false);
        }
    }
}
