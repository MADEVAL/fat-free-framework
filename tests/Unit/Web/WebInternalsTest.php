<?php

declare(strict_types=1);

namespace Tests\Unit\Web;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Web;

/**
 * Web class: engine, subst, slug, filler, mime, send, minify, progress,
 * acceptable, plus exhaustive mime-extension and slug variants.
 */
final class WebInternalsTest extends TestCase
{
    private Web $web;
    private ?string $savedAccept = null;

    protected function setUp(): void
    {
        $this->web       = Web::instance();
        $this->savedAccept = $_SERVER['HTTP_ACCEPT'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->savedAccept === null) {
            unset($_SERVER['HTTP_ACCEPT']);
        } else {
            $_SERVER['HTTP_ACCEPT'] = $this->savedAccept;
        }
    }

    // -- transport ----------------------------------------------------------

    public function testEngineFallsBackToAvailableTransport(): void
    {
        $picked = $this->web->engine('curl');
        $this->assertContains($picked, ['curl', 'stream', 'socket']);
    }

    // -- header helpers -----------------------------------------------------

    public function testSubstReplacesExistingHeader(): void
    {
        $headers = ['Host: a.example', 'Accept: */*'];
        $this->web->subst($headers, 'Host: b.example');
        $this->assertContains('Host: b.example', $headers);
        $hostCount = 0;
        foreach ($headers as $h) {
            if (stripos($h, 'Host:') === 0) {
                $hostCount++;
            }
        }
        $this->assertSame(1, $hostCount);
    }

    public function testSubstAppendsArrayOfHeaders(): void
    {
        $headers = [];
        $this->web->subst($headers, ['X-A: 1', 'X-B: 2']);
        $this->assertSame(['X-A: 1', 'X-B: 2'], $headers);
    }

    // -- slug ---------------------------------------------------------------

    public function testSlugAsciiSafe(): void
    {
        $this->assertSame('hello-world', $this->web->slug('Hello World!'));
    }

    public function testSlugTransliteratesUnicode(): void
    {
        $out = $this->web->slug('Привет мир');
        $this->assertNotEmpty($out);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $out);
    }

    public function testSlugTransliteratesAccents(): void
    {
        $this->assertSame('cafe-au-lait', $this->web->slug('Café au lait'));
    }

    public function testSlugCustomSeparator(): void
    {
        $this->assertSame('hello_world', $this->web->slug('Hello World', '_'));
    }

    public function testBasicLowercase(): void
    {
        $this->assertSame('hello-world', $this->web->slug('Hello World'));
    }

    public function testStripsSpecialChars(): void
    {
        $this->assertSame('a-b-c', $this->web->slug('a! @b# $c%'));
    }

    public function testCollapsesMultipleSeparators(): void
    {
        $this->assertSame('a-b', $this->web->slug('a   ---   b'));
    }

    public function testTrimsLeadingTrailingSeparators(): void
    {
        $out = $this->web->slug('---hello---');
        $this->assertSame('hello', $out);
    }

    public function testTransliteratesLatinDiacritics(): void
    {
        $out = $this->web->slug('Crème Brûlée');
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $out);
        $this->assertStringContainsString('creme', $out);
    }

    public function testEmptyInputProducesEmpty(): void
    {
        $this->assertSame('', $this->web->slug(''));
    }

    // -- filler -------------------------------------------------------------

    public function testFillerProducesText(): void
    {
        $text = $this->web->filler(2, 5);
        $this->assertNotEmpty($text);
        $this->assertIsString($text);
    }

    public function testFillerWithStdFalseOmitsLoremIpsumPrefix(): void
    {
        $text = $this->web->filler(2, 10, false);
        $this->assertIsString($text);
        $this->assertNotEmpty($text);
        $this->assertStringNotContainsString('Lorem ipsum', $text);
    }

    // -- mime ---------------------------------------------------------------

    public function testMimeFromExtension(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mt-' . uniqid() . '.css';
        file_put_contents($tmp, 'body{}');
        try {
            $this->assertStringContainsString('css', $this->web->mime($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function testMimeUnknownReturnsOctetStream(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mt-' . uniqid() . '.zzunknown';
        file_put_contents($tmp, 'x');
        try {
            $this->assertSame('application/octet-stream', $this->web->mime($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public static function extensions(): array
    {
        return [
            'png'  => ['photo.png', 'image/png'],
            'jpg'  => ['snap.jpg', 'image/jpeg'],
            'jpeg' => ['snap.jpeg', 'image/jpeg'],
            'gif'  => ['anim.gif', 'image/gif'],
            'svg'  => ['vec.svg', 'image/svg+xml'],
            'css'  => ['style.css', 'text/css'],
            'js'   => ['app.js', 'application/x-javascript'],
            'pdf'  => ['doc.pdf', 'application/pdf'],
            'html' => ['page.html', 'text/html'],
            'htm'  => ['page.htm', 'text/html'],
            'txt'  => ['notes.txt', 'text/plain'],
            'xml'  => ['data.xml', 'application/xml'],
            'mp3'  => ['song.mp3', 'audio/mpeg'],
            'wav'  => ['snip.wav', 'audio/wav'],
            'zip'  => ['arc.zip', 'application/x-zip-compressed'],
            'gz'   => ['arc.gz', 'application/x-gzip'],
            'bmp'  => ['img.bmp', 'image/bmp'],
            'tiff' => ['img.tiff', 'image/tiff'],
            'doc'  => ['doc.doc', 'application/msword'],
            'rtf'  => ['mem.rtf', 'application/rtf'],
        ];
    }

    #[DataProvider('extensions')]
    public function testMimeMatchesExtension(string $file, string $expected): void
    {
        $this->assertSame($expected, $this->web->mime($file));
    }

    public function testUnknownExtensionFallsBackToOctetStream(): void
    {
        $this->assertSame('application/octet-stream', $this->web->mime('thing.xyz'));
    }

    public function testFileWithNoExtensionFallsBackToOctetStream(): void
    {
        $this->assertSame('application/octet-stream', $this->web->mime('Makefile'));
    }

    // -- send ---------------------------------------------------------------

    public function testSendReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->web->send('definitely-missing-' . uniqid() . '.txt'));
    }

    public function testSendReturnsFileSizeAndOutputsContent(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'send-' . uniqid() . '.txt';
        file_put_contents($tmp, 'hello send');
        try {
            // $flush=false avoids ob_end_clean() so PHPUnit buffering is intact.
            ob_start();
            $result = $this->web->send($tmp, null, 0, true, null, false);
            $body   = ob_get_clean();
            $this->assertSame(filesize($tmp), $result);
            $this->assertSame('hello send', $body);
        } finally {
            @unlink($tmp);
        }
    }

    // -- minify -------------------------------------------------------------

    public function testMinifyStripsCssComments(): void
    {
        $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'minify-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($dir, 0777, true);
        $file = 'style.css';
        file_put_contents($dir . $file, "/* strip me */\nbody { color: red; }\n");
        $f3      = \Base::instance();
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', false);
        try {
            $result = $this->web->minify($file, null, false, $dir);
            $this->assertStringNotContainsString('strip me', $result);
            $this->assertStringContainsString('color', $result);
        } finally {
            $f3->set('CACHE', $prevCache);
            @unlink($dir . $file);
            @rmdir($dir);
        }
    }

    public function testMinifyStripsJsComments(): void
    {
        $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'minify-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($dir, 0777, true);
        $file = 'app.js';
        file_put_contents($dir . $file, "// single line\nvar x=1;/* block */var y=2;\n");
        $f3        = \Base::instance();
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', false);
        try {
            $result = $this->web->minify($file, null, false, $dir);
            $this->assertStringNotContainsString('single line', $result);
            $this->assertStringNotContainsString('block', $result);
            $this->assertStringContainsString('x=1', $result);
        } finally {
            $f3->set('CACHE', $prevCache);
            @unlink($dir . $file);
            @rmdir($dir);
        }
    }

    // -- progress -----------------------------------------------------------

    public function testProgressReturnsFalseWhenUploadProgressDisabled(): void
    {
        // When session.upload_progress.enabled is off, progress() returns false.
        $prev = ini_get('session.upload_progress.enabled');
        ini_set('session.upload_progress.enabled', '0');
        try {
            $this->assertFalse($this->web->progress('some-id'));
        } finally {
            ini_set('session.upload_progress.enabled', $prev);
        }
    }

    // -- acceptable ---------------------------------------------------------

    public function testAcceptableReturnsArrayWhenNoListPassed(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/json;q=0.9';
        $result = $this->web->acceptable();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('text/html', $result);
        $this->assertArrayHasKey('application/json', $result);
    }

    public function testAcceptableMatchesBestQualityType(): void
    {
        // text/html has implicit q=1.0; application/json has q=0.9.
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/json;q=0.9';
        $result = $this->web->acceptable('text/html,application/json');
        $this->assertSame('text/html', $result);
    }

    public function testAcceptableReturnsFalseWhenNoMatch(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->assertFalse($this->web->acceptable('application/json'));
    }

    public function testAcceptableDefaultsToWildcardWhenHeaderAbsent(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        $result = $this->web->acceptable();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('*/*', $result);
        $this->assertSame(1, $result['*/*']);
    }

    public function testAcceptableWildcardMatchesAnyType(): void
    {
        // With */* as the only accepted type, any MIME in the list should match.
        unset($_SERVER['HTTP_ACCEPT']);
        $result = $this->web->acceptable('application/json');
        $this->assertSame('application/json', $result);
    }

    public function testParsesAcceptHeader(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9';
        $out = $this->web->acceptable();
        $this->assertIsArray($out);
        $this->assertArrayHasKey('text/html', $out);
        $this->assertArrayHasKey('application/xml', $out);
        $this->assertEqualsWithDelta(0.9, (float) $out['application/xml'], 0.0001);
    }

    public function testWildcardWhenNoHeader(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        $out = $this->web->acceptable();
        $this->assertArrayHasKey('*/*', $out);
    }

    public function testSelectBestMatch(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json,text/html;q=0.5';
        $best = $this->web->acceptable(['text/html', 'application/json']);
        $this->assertSame('application/json', $best);
    }

    public function testReturnsFalseWhenNoListMatch(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/plain';
        $this->assertFalse($this->web->acceptable(['image/png']));
    }
}
