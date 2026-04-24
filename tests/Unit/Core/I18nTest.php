<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Base::language() and Base::lexicon() -- i18n subsystem.
 * These methods are entirely untested: language() parses Accept-Language
 * strings and sets LANGUAGE; lexicon() loads dictionary files (.php,
 * .json, .ini) and merges them according to the active language list.
 */
final class I18nTest extends TestCase
{
    private Base $f3;
    private string $dir;
    private string $origFallback;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        $this->origFallback = $this->f3->get('FALLBACK') ?: 'en';
        $this->f3->set('FALLBACK', 'en');
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3i18n-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        // Restore language state to a neutral baseline.
        $this->f3->set('FALLBACK', $this->origFallback);
        $this->f3->language('');
    }

    // -- language() ---------------------------------------------------------

    public function testLanguageReturnsParsedCode(): void
    {
        $result = $this->f3->language('en');
        $this->assertStringContainsString('en', $result);
    }

    public function testLanguageSetsHiveKey(): void
    {
        $this->f3->language('de');
        $this->assertStringContainsString('de', $this->f3->get('LANGUAGE'));
    }

    public function testLanguageFallbackAppendedToLanguageList(): void
    {
        $this->f3->set('FALLBACK', 'en');
        $result = $this->f3->language('fr');
        $this->assertStringContainsString('fr', $result);
        $this->assertStringContainsString('en', $result);
    }

    public function testLanguageRegionCodeIncludesBase(): void
    {
        $result = $this->f3->language('zh-CN');
        // Both zh and zh-CN (or zh-CN, zh) must appear
        $this->assertStringContainsString('zh', $result);
    }

    public function testLanguageEmptyStringFallsBackToFallback(): void
    {
        $this->f3->set('FALLBACK', 'en');
        $result = $this->f3->language('');
        $this->assertStringContainsString('en', $result);
    }

    public function testLanguageDeduplicatesCodes(): void
    {
        // Passing 'en' when FALLBACK='en' should not produce 'en,en'
        $result = $this->f3->language('en');
        $parts = array_filter(explode(',', $result));
        $unique = array_unique($parts);
        $this->assertSame(count($unique), count($parts), 'Duplicate language codes in LANGUAGE.');
    }

    // -- lexicon() ----------------------------------------------------------

    public function testLexiconLoadsPhpFile(): void
    {
        file_put_contents($this->dir . 'en.php', '<?php return ["hello" => "Hello", "bye" => "Goodbye"];');
        $this->f3->language('en');
        $lex = $this->f3->lexicon($this->dir);
        $this->assertSame('Hello',   $lex['hello']);
        $this->assertSame('Goodbye', $lex['bye']);
    }

    public function testLexiconLoadsJsonFile(): void
    {
        file_put_contents($this->dir . 'en.json', json_encode(['key1' => 'val1', 'key2' => 'val2']));
        $this->f3->language('en');
        $lex = $this->f3->lexicon($this->dir);
        $this->assertSame('val1', $lex['key1']);
        $this->assertSame('val2', $lex['key2']);
    }

    public function testLexiconLoadsIniFile(): void
    {
        file_put_contents($this->dir . 'en.ini', "greet = Hi there\nfare = Farewell\n");
        $this->f3->language('en');
        $lex = $this->f3->lexicon($this->dir);
        $this->assertSame('Hi there', $lex['greet']);
        $this->assertSame('Farewell', $lex['fare']);
    }

    public function testLexiconReturnsEmptyArrayForUnknownLanguage(): void
    {
        // No files exist for this isolated dir / language
        $this->f3->language('xx');
        $lex = $this->f3->lexicon($this->dir);
        $this->assertIsArray($lex);
        $this->assertEmpty($lex);
    }

    public function testLexiconPrimaryLanguageWinsOverFallback(): void
    {
        file_put_contents($this->dir . 'en.php', '<?php return ["a" => "A-en", "b" => "B-en"];');
        file_put_contents($this->dir . 'fr.php', '<?php return ["a" => "A-fr"];');
        $this->f3->set('FALLBACK', 'en');
        $this->f3->language('fr');
        $lex = $this->f3->lexicon($this->dir);
        // 'a' from fr wins; 'b' comes from en fallback only
        $this->assertSame('A-fr', $lex['a']);
        $this->assertSame('B-en', $lex['b']);
    }

    public function testLexiconIniSectionPrefixing(): void
    {
        $ini = "[section]\nkey = value\n";
        file_put_contents($this->dir . 'en.ini', $ini);
        $this->f3->language('en');
        $lex = $this->f3->lexicon($this->dir);
        $this->assertSame('value', $lex['section.key']);
    }
}
