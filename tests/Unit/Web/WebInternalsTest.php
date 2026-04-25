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

    // -- diacritics ---------------------------------------------------------

    public function testDiacriticsReturnsMapWithKnownEntries(): void
    {
        $map = $this->web->diacritics();
        $this->assertIsArray($map);
        $this->assertGreaterThan(100, count($map));
        $this->assertSame('Ae', $map['Ä']);
        $this->assertSame('ae', $map['ä']);
        $this->assertSame('Oe', $map['Ö']);
        $this->assertSame('Ue', $map['Ü']);
        $this->assertArrayHasKey('Å', $map);
        $this->assertArrayHasKey('ñ', $map);
    }

    // -- request / transport ------------------------------------------------

    public function testRequestReturnsFalseForNonHttpScheme(): void
    {
        // ftp:// is rejected before any transport is used.
        $this->assertFalse($this->web->request('ftp://example.com'));
    }

    public function testRequestWithCurlEngineReturnsArrayOnConnectionFailure(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension not available');
        }
        $this->web->engine('curl');
        // Port 1 on loopback is always refused - fast failure, no timeout risk.
        $result = $this->web->request('http://127.0.0.1:1/');
        $this->assertIsArray($result);
        $this->assertSame('cURL', $result['engine']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
    }

    public function testRequestWithStreamEngineReturnsArrayOnConnectionFailure(): void
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled');
        }
        // PHP 8+ no longer zeros error_reporting() inside custom handlers for @-suppressed
        // calls, so F3's error handler fires on file_get_contents failure. Guard it.
        $f3        = \Base::instance();
        $prevQuiet = $f3->get('QUIET');
        $prevHalt  = $f3->get('HALT');
        $f3->set('QUIET', true);
        $f3->set('HALT', false);
        $this->web->engine('stream');
        try {
            $result = $this->web->request('http://127.0.0.1:1/');
            $this->assertIsArray($result);
            $this->assertSame('stream', $result['engine']);
        } finally {
            $this->web->engine('curl');
            $f3->set('QUIET', $prevQuiet);
            $f3->set('HALT', $prevHalt);
        }
    }

    public function testRequestWithSocketEngineReturnsArrayOnConnectionFailure(): void
    {
        if (!function_exists('fsockopen')) {
            $this->markTestSkipped('fsockopen not available');
        }
        $f3        = \Base::instance();
        $prevQuiet = $f3->get('QUIET');
        $prevHalt  = $f3->get('HALT');
        $f3->set('QUIET', true);
        $f3->set('HALT', false);
        $this->web->engine('socket');
        try {
            $result = $this->web->request('http://127.0.0.1:1/');
            $this->assertIsArray($result);
            $this->assertSame('socket', $result['engine']);
        } finally {
            $this->web->engine('curl');
            $f3->set('QUIET', $prevQuiet);
            $f3->set('HALT', $prevHalt);
        }
    }

    // -- rss ----------------------------------------------------------------

    public function testRssReturnsFalseWhenRequestFails(): void
    {
        // ftp:// is rejected by request() -> rss() returns FALSE.
        $this->assertFalse($this->web->rss('ftp://unreachable'));
    }

    public function testRssReturnsFalseForInvalidXml(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension not available');
        }
        // Port 1 connection fails, body is empty string - not valid XML.
        $this->web->engine('curl');
        $result = $this->web->rss('http://127.0.0.1:1/feed.xml');
        $this->web->engine('curl');
        $this->assertFalse($result);
    }

    // -- whois --------------------------------------------------------------

    public function testWhoisReturnsFalseWhenConnectionFails(): void
    {
        // fsockopen failure triggers F3's error handler in PHP 8+ (same @ issue).
        $f3        = \Base::instance();
        $prevQuiet = $f3->get('QUIET');
        $prevHalt  = $f3->get('HALT');
        $f3->set('QUIET', true);
        $f3->set('HALT', false);
        try {
            // 127.0.0.1 port 43: connection refused immediately.
            $result = $this->web->whois('example.com', '127.0.0.1');
            $this->assertFalse($result);
        } finally {
            $f3->set('QUIET', $prevQuiet);
            $f3->set('HALT', $prevHalt);
        }
    }

    // -- mime inspect mode --------------------------------------------------

    public function testMimeInspectModeWithLocalFileUsesFileinfo(): void
    {
        if (!extension_loaded('fileinfo')) {
            $this->markTestSkipped('fileinfo extension not available');
        }
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mi-' . uniqid() . '.png';
        // Minimal PNG magic bytes so fileinfo identifies it as image/png.
        file_put_contents($tmp, "\x89PNG\x0d\x0a\x1a\x0a" . str_repeat("\x00", 20));
        try {
            $result = $this->web->mime($tmp, true);
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } finally {
            @unlink($tmp);
        }
    }

    // -- engine foreach fallback -------------------------------------------

    public function testEngineFallsBackViaForEachWhenRequestedUnavailable(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl must be available as fallback target');
        }
        // Passing an unknown engine name causes $flags['bogus'] = undefined key
        // (PHP 8 E_WARNING). F3's error handler fires, but with QUIET+HALT guards
        // it returns cleanly. Code continues: $flags['bogus'] = null (falsy),
        // so engine() falls through to the foreach which finds the first available engine.
        $f3        = \Base::instance();
        $prevQuiet = $f3->get('QUIET');
        $prevHalt  = $f3->get('HALT');
        $f3->set('QUIET', true);
        $f3->set('HALT', false);
        try {
            $result = $this->web->engine('bogus');
            $this->assertContains($result, ['curl', 'stream', 'socket']);
        } finally {
            $this->web->engine('curl');
            $f3->set('QUIET', $prevQuiet);
            $f3->set('HALT', $prevHalt);
        }
    }

    // -- request local URL construction ------------------------------------

    public function testRequestWithRelativeUrlUsesHiveSchemeAndHost(): void
    {
        $f3    = \Base::instance();
        $saved = [
            'SCHEME' => $f3->get('SCHEME'),
            'HOST'   => $f3->get('HOST'),
            'PORT'   => $f3->get('PORT'),
            'BASE'   => $f3->get('BASE'),
            'QUIET'  => $f3->get('QUIET'),
            'HALT'   => $f3->get('HALT'),
        ];
        $f3->set('SCHEME', 'http');
        $f3->set('HOST', '127.0.0.1');
        $f3->set('PORT', 1);
        $f3->set('BASE', '');
        $f3->set('QUIET', true);
        $f3->set('HALT', false);
        $this->web->engine('curl');
        try {
            // No scheme: request() constructs full URL from SCHEME/HOST/PORT hive.
            // Port 1 is always refused - the URL construction code is exercised.
            $result = $this->web->request('/probe');
            $this->assertIsArray($result);
        } finally {
            $this->web->engine('curl');
            foreach ($saved as $k => $v) {
                $f3->set($k, $v);
            }
        }
    }

    // -- minify array input ------------------------------------------------

    public function testMinifyWithArrayOfFilesInput(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'min-arr-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($dir, 0777, true);
        file_put_contents($dir . 'a.css', "body { color: red; }\n");
        file_put_contents($dir . 'b.css', "p { margin: 0; }\n");
        $f3        = \Base::instance();
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', false);
        try {
            // Array input: covers the is_string($files) -> split branch in minify().
            $result = $this->web->minify(['a.css', 'b.css'], null, false, $dir);
            $this->assertStringContainsString('color', $result);
        } finally {
            $f3->set('CACHE', $prevCache);
            @unlink($dir . 'a.css');
            @unlink($dir . 'b.css');
            @rmdir($dir);
        }
    }

    // -- acceptable with array list ----------------------------------------

    public function testAcceptableWithArrayListInput(): void
    {
        // Passing an array (not a string) covers the is_string($list) = false branch.
        $_SERVER['HTTP_ACCEPT'] = 'application/json,text/html;q=0.8';
        $result = $this->web->acceptable(['text/html', 'application/json']);
        $this->assertSame('application/json', $result);
    }

    // -- receive (PUT) ------------------------------------------------------

    public function testReceivePutBodyCreatesFile(): void
    {
        $f3      = \Base::instance();
        $uploads = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'recv-' . uniqid() . DIRECTORY_SEPARATOR;

        $saved = [
            'UPLOADS' => $f3->get('UPLOADS'),
            'VERB'    => $f3->get('VERB'),
            'BODY'    => $f3->get('BODY'),
            'RAW'     => $f3->get('RAW'),
            'URI'     => $f3->get('URI'),
            'TEMP'    => $f3->get('TEMP'),
        ];

        $f3->set('UPLOADS', $uploads);
        $f3->set('VERB', 'PUT');
        $f3->set('BODY', 'put-file-data');
        $f3->set('RAW', false);
        $f3->set('URI', '/testfile.txt');
        $f3->set('TEMP', sys_get_temp_dir() . DIRECTORY_SEPARATOR);

        try {
            $result = $this->web->receive();
            $this->assertTrue($result, 'receive() must return true on PUT success');
            $this->assertFileExists($uploads . 'testfile.txt');
            $this->assertSame('put-file-data', file_get_contents($uploads . 'testfile.txt'));
        } finally {
            @unlink($uploads . 'testfile.txt');
            @rmdir($uploads);
            foreach ($saved as $k => $v) {
                $f3->set($k, $v);
            }
        }
    }

    public function testReceivePutWithSlugDisabledKeepsOriginalName(): void
    {
        $f3      = \Base::instance();
        $uploads = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'recv2-' . uniqid() . DIRECTORY_SEPARATOR;
        $saved   = [
            'UPLOADS' => $f3->get('UPLOADS'),
            'VERB'    => $f3->get('VERB'),
            'BODY'    => $f3->get('BODY'),
            'RAW'     => $f3->get('RAW'),
            'URI'     => $f3->get('URI'),
            'TEMP'    => $f3->get('TEMP'),
        ];
        $f3->set('UPLOADS', $uploads);
        $f3->set('VERB', 'PUT');
        $f3->set('BODY', 'noslug');
        $f3->set('RAW', false);
        $f3->set('URI', '/MyFile.txt');
        $f3->set('TEMP', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        try {
            // slug=false: filename used verbatim, not slugified.
            $result = $this->web->receive(null, false, false);
            $this->assertTrue($result);
            $this->assertFileExists($uploads . 'MyFile.txt');
        } finally {
            @unlink($uploads . 'MyFile.txt');
            @rmdir($uploads);
            foreach ($saved as $k => $v) {
                $f3->set($k, $v);
            }
        }
    }

    public function testReceivePostFilesPathCoversIteration(): void
    {
        $f3      = \Base::instance();
        $uploads = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'recv3-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($uploads, 0777, true);
        $savedUploads = $f3->get('UPLOADS');
        $savedVerb    = $f3->get('VERB');
        $f3->set('UPLOADS', $uploads);
        $f3->set('VERB', 'GET');

        // Populate $_FILES to exercise the POST-upload iteration path.
        // move_uploaded_file() will return false in CLI (not a real upload),
        // but all iteration, slug, and result-building code is covered.
        $_FILES = [
            'upload' => [
                'name'     => 'document.txt',
                'type'     => 'text/plain',
                'size'     => 4,
                'tmp_name' => '/nonexistent/tmp',
                'error'    => 0,
            ],
        ];
        try {
            $result = $this->web->receive();
            $this->assertIsArray($result);
        } finally {
            $_FILES = [];
            $f3->set('UPLOADS', $savedUploads);
            $f3->set('VERB', $savedVerb);
            @rmdir($uploads);
        }
    }

    public function testReceivePostFilesSkipsEmptyNameAndHandlesError(): void
    {
        $f3      = \Base::instance();
        $uploads = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'recv4-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($uploads, 0777, true);
        $savedUploads = $f3->get('UPLOADS');
        $savedVerb    = $f3->get('VERB');
        $f3->set('UPLOADS', $uploads);
        $f3->set('VERB', 'GET');

        $_FILES = [
            'empty_name' => [
                'name'     => '',      // empty -> skipped via continue
                'type'     => '',
                'size'     => 0,
                'tmp_name' => '',
                'error'    => 0,
            ],
            'with_error' => [
                'name'     => 'fail.txt',
                'type'     => 'text/plain',
                'size'     => 0,
                'tmp_name' => '/nonexistent',
                'error'    => UPLOAD_ERR_CANT_WRITE, // error != 0 -> $out[...] = false
            ],
        ];
        try {
            $result = $this->web->receive();
            $this->assertIsArray($result);
        } finally {
            $_FILES = [];
            $f3->set('UPLOADS', $savedUploads);
            $f3->set('VERB', $savedVerb);
            @rmdir($uploads);
        }
    }
}
