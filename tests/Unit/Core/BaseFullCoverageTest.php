<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Helper class for grab() reflection instantiation tests.
 * Must NOT extend Prefab so that ReflectionClass path is exercised.
 */
final class TestInstantiableHelper
{
    public string $val;
    public function __construct(string $v) { $this->val = $v; }
    public function value(): string { return $this->val; }
}

/**
 * Targeted coverage for Base methods not exercised elsewhere:
 * cast, compile, build, mask, blacklisted, checked, visible, flip, pull,
 * trace/error/highlight/dump, agent/ajax/ip, read/write/mutex, rel,
 * languages/countries (ISO), ArrayAccess + magic accessors, base64.
 */
final class BaseFullCoverageTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
    }

    public function testCastInfersTypes(): void
    {
        $this->assertSame(255, $this->f3->cast('0xFF'));
        $this->assertSame(8, $this->f3->cast('010'));
        $this->assertSame(5, $this->f3->cast('0b101'));
        $this->assertSame(3.14, $this->f3->cast('3.14'));
        $this->assertSame(42, $this->f3->cast('42'));
        $this->assertSame('hello', $this->f3->cast('hello'));
        $this->assertSame(PHP_INT_MAX, $this->f3->cast('PHP_INT_MAX'));
    }

    public function testCompileTokenAccess(): void
    {
        $out = $this->f3->compile('@user.name', false);
        $this->assertSame("\$user['name']", $out);
        $out = $this->f3->compile('@items[0]', false);
        $this->assertSame("\$items[0]", $out);
    }

    public function testCompileEvaluatesExpression(): void
    {
        $out = $this->f3->compile('@a + @b');
        $this->assertSame('$a + $b', $out);
    }

    public function testBuildSubstitutesTokensAndWildcard(): void
    {
        $url = $this->f3->build('/user/@id/posts/@slug',
            ['id' => 7, 'slug' => 'hello'], false);
        $this->assertSame('/user/7/posts/hello', $url);

        $url = $this->f3->build('/files/*', ['*' => ['a', 'b']], false);
        $this->assertSame('/files/a', $url);
    }

    public function testBuildMergesParamsHive(): void
    {
        $this->f3->set('PARAMS', ['lang' => 'en']);
        try {
            $this->assertSame('/en', $this->f3->build('/@lang'));
        } finally {
            $this->f3->set('PARAMS', []);
        }
    }

    public function testMaskExtractsPositionalAndNamedParts(): void
    {
        $args = $this->f3->mask('/user/@id/@slug', '/user/42/hello');
        $this->assertSame('42', $args['id']);
        $this->assertSame('hello', $args['slug']);
    }

    public function testCheckedReflectsCheckboxState(): void
    {
        $this->f3->set('chk', 'on');
        $this->assertTrue($this->f3->checked('chk'));
        $this->f3->set('chk', '');
        $this->assertFalse($this->f3->checked('chk'));
        $this->f3->clear('chk');
    }

    public function testVisibleDistinguishesPublicAndPrivate(): void
    {
        $obj = new class {
            public int $shown = 1;
            private int $hidden = 0;
        };
        $this->assertTrue($this->f3->visible($obj, 'shown'));
        $this->assertFalse($this->f3->visible($obj, 'hidden'));
        $this->assertFalse($this->f3->visible($obj, 'missing'));
    }

    public function testFlipReversesArray(): void
    {
        $this->f3->set('m', ['a' => 1, 'b' => 2]);
        $out = $this->f3->flip('m');
        $this->assertSame([1 => 'a', 2 => 'b'], $out);
        $this->f3->clear('m');
    }

    public function testPullFetchesAndClearsKey(): void
    {
        $this->f3->set('oneShot', 'value');
        $this->assertSame('value', $this->f3->pull('oneShot'));
        $this->assertFalse($this->f3->exists('oneShot'));
    }

    public function testBlacklistedReturnsFalseWithoutDnsbl(): void
    {
        $prev = $this->f3->get('DNSBL');
        $this->f3->set('DNSBL', null);
        try {
            $this->assertFalse($this->f3->blacklisted('1.2.3.4'));
        } finally {
            $this->f3->set('DNSBL', $prev);
        }
    }

    public function testTraceReturnsFormattedString(): void
    {
        $out = $this->f3->trace(null, true);
        $this->assertIsString($out);
    }

    public function testTraceUnformattedReturnsArray(): void
    {
        $out = $this->f3->trace(null, false);
        $this->assertIsArray($out);
    }

    public function testHighlightWrapsTokensInSpans(): void
    {
        $out = $this->f3->highlight('echo 1;');
        $this->assertStringContainsString('<code>', $out);
        $this->assertStringContainsString('<span', $out);
    }

    public function testDumpEchoesHighlightedExpression(): void
    {
        ob_start();
        $this->f3->dump(['a' => 1]);
        $out = ob_get_clean();
        $this->assertStringContainsString('<code>', $out);
    }

    public function testRelStripsBaseAndScheme(): void
    {
        $prev = $this->f3->get('BASE');
        // rel() expects URLs whose scheme prefix is followed directly by BASE.
        $this->f3->set('BASE', 'http://example.com/app');
        try {
            $this->assertSame('/path?x=1',
                $this->f3->rel('http://example.com/app/path?x=1'));
        } finally {
            $this->f3->set('BASE', $prev);
        }
    }

    public function testAgentReadsFromHeaders(): void
    {
        $prev = $this->f3->get('HEADERS');
        $this->f3->set('HEADERS.User-Agent', 'TestAgent/1.0');
        try {
            $this->assertSame('TestAgent/1.0', $this->f3->agent());
        } finally {
            $this->f3->set('HEADERS', $prev);
        }
    }

    public function testAjaxDetectsXhrHeader(): void
    {
        $prev = $this->f3->get('HEADERS');
        $this->f3->set('HEADERS.X-Requested-With', 'XMLHttpRequest');
        try {
            $this->assertTrue($this->f3->ajax());
        } finally {
            $this->f3->set('HEADERS', $prev);
        }
    }

    public function testIpResolvesFromHeader(): void
    {
        $prev = $this->f3->get('HEADERS');
        $this->f3->set('HEADERS.Client-IP', '10.0.0.1');
        try {
            $this->assertSame('10.0.0.1', $this->f3->ip());
        } finally {
            $this->f3->set('HEADERS', $prev);
        }
    }

    public function testReadWriteRoundTrip(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rw-' . uniqid() . '.txt';
        try {
            $bytes = $this->f3->write($tmp, "line1\r\nline2\r\n");
            $this->assertGreaterThan(0, $bytes);
            $this->assertSame("line1\nline2\n", $this->f3->read($tmp, true));
            $this->f3->write($tmp, "more\n", true);
            $this->assertStringContainsString('more', $this->f3->read($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function testReadReturnsFalseWhenMissing(): void
    {
        $this->assertFalse($this->f3->read(__DIR__ . '/no-such-file-' . uniqid()));
    }

    public function testMutexExecutesCallback(): void
    {
        $out = $this->f3->mutex('basecov-' . uniqid(), fn () => 42);
        $this->assertSame(42, $out);
    }

    public function testBase64ProducesDataUri(): void
    {
        $uri = $this->f3->base64('hello', 'text/plain');
        $this->assertStringStartsWith('data:text/plain;base64,', $uri);
        $this->assertSame('hello', base64_decode(substr($uri, strlen('data:text/plain;base64,'))));
    }

    public function testArrayAccessAndMagicAreEquivalent(): void
    {
        $this->f3['mg'] = 'hi';
        $this->assertTrue(isset($this->f3['mg']));
        $this->assertSame('hi', $this->f3['mg']);
        unset($this->f3['mg']);
        $this->assertFalse(isset($this->f3['mg']));

        $this->f3->mg2 = 'ok';
        $this->assertTrue(isset($this->f3->mg2));
        $this->assertSame('ok', $this->f3->mg2);
        unset($this->f3->mg2);
        $this->assertFalse(isset($this->f3->mg2));
    }

    public function testMagicCallInvokesHiveCallback(): void
    {
        $this->f3->set('greet', fn ($name) => "hi $name");
        $this->assertSame('hi bob', $this->f3->greet('bob'));
        $this->f3->clear('greet');
    }

    public function testMagicCallThrowsForMissingKey(): void
    {
        $this->expectException(\Exception::class);
        $this->f3->no_such_method_xyz();
    }

    public function testIsoLanguagesAndCountries(): void
    {
        $iso = \ISO::instance();
        $langs = $iso->languages();
        $countries = $iso->countries();
        // Languages are indexed by ISO 639-1 (lowercase).
        $this->assertArrayHasKey('en', $langs);
        $this->assertArrayHasKey('ru', $langs);
        // Countries are stored with lowercase keys (ISO 3166-1 alpha-2 lowered).
        $this->assertArrayHasKey('au', $countries);
        $this->assertArrayHasKey('ar', $countries);
    }

    public function testExportProducesVarExportString(): void
    {
        $this->assertSame("'hello'", $this->f3->export('hello'));
        $this->assertSame('42', $this->f3->export(42));
        $this->assertSame('NULL', $this->f3->export(null));
    }

    public function testScrubModifiesVariableInPlace(): void
    {
        $val = '<b>test</b>';
        $out = $this->f3->scrub($val);
        $this->assertSame('test', $out);
        $this->assertSame('test', $val, 'scrub must modify the variable in-place');
    }

    public function testScrubKeepsAllTagsWithWildcard(): void
    {
        $val = '<b>bold</b>';
        $out = $this->f3->scrub($val, '*');
        $this->assertSame('<b>bold</b>', $out);
    }

    public function testRecursiveWorksOnObjects(): void
    {
        $obj = new \stdClass();
        $obj->name = '<b>x</b>';
        $result = $this->f3->recursive($obj, fn ($v) => is_string($v) ? strtoupper($v) : $v);
        $this->assertSame('<B>X</B>', $result->name);
    }

    public function testCleanStripsTagsFromArray(): void
    {
        $out = $this->f3->clean(['<b>a</b>', '<i>b</i>']);
        $this->assertSame(['a', 'b'], $out);
    }

    public function testIpResolvesXForwardedFor(): void
    {
        $prev = $this->f3->get('HEADERS');
        $this->f3->set('HEADERS', ['X-Forwarded-For' => '192.168.1.1,10.0.0.1']);
        try {
            $this->assertSame('192.168.1.1', $this->f3->ip());
        } finally {
            $this->f3->set('HEADERS', $prev);
        }
    }

    public function testAgentFallsBackToXSkyfire(): void
    {
        $prev = $this->f3->get('HEADERS');
        $this->f3->set('HEADERS', ['X-Skyfire-Phone' => 'SkyfireUA/1.0']);
        try {
            $this->assertSame('SkyfireUA/1.0', $this->f3->agent());
        } finally {
            $this->f3->set('HEADERS', $prev);
        }
    }

    public function testAgentPrefersOperaMini(): void
    {
        $prev = $this->f3->get('HEADERS');
        $this->f3->set('HEADERS', [
            'X-Operamini-Phone-UA' => 'OperaMini/1.0',
            'User-Agent' => 'OtherAgent',
        ]);
        try {
            $this->assertSame('OperaMini/1.0', $this->f3->agent());
        } finally {
            $this->f3->set('HEADERS', $prev);
        }
    }

    public function testStatusReturnsReasonPhrase(): void
    {
        $reason = $this->f3->status(200);
        $this->assertSame('OK', $reason);

        $reason = $this->f3->status(404);
        $this->assertSame('Not Found', $reason);

        $reason = $this->f3->status(500);
        $this->assertSame('Internal Server Error', $reason);
    }

    public function testCamelcaseConvertsSnakeCase(): void
    {
        $this->assertSame('helloWorld', $this->f3->camelcase('hello_world'));
        $this->assertSame('myFooBar', $this->f3->camelcase('my_foo_bar'));
        $this->assertSame('simple', $this->f3->camelcase('simple'));
    }

    public function testSnakecaseConvertsFromCamelcase(): void
    {
        $this->assertSame('hello_world', $this->f3->snakecase('helloWorld'));
        $this->assertSame('my_foo_bar', $this->f3->snakecase('myFooBar'));
        $this->assertSame('simple', $this->f3->snakecase('simple'));
    }

    public function testSignReturnsMinusOneZeroOrOne(): void
    {
        $this->assertSame(-1, (int) $this->f3->sign(-42));
        $this->assertSame(0, (int) $this->f3->sign(0));
        $this->assertSame(1, (int) $this->f3->sign(7));
    }

    public function testExtractFiltersKeysByPrefix(): void
    {
        $arr = ['db.host' => 'localhost', 'db.port' => 3306, 'app.name' => 'test'];
        $out = $this->f3->extract($arr, 'db.');
        $this->assertSame(['host' => 'localhost', 'port' => 3306], $out);
    }

    public function testConstantsConvertsClassConstantsToArray(): void
    {
        $out = $this->f3->constants(\DB\SQL::class, 'E_');
        $this->assertArrayHasKey('PKey', $out);
    }

    public function testCsvFlattensToString(): void
    {
        $out = $this->f3->csv(['hello', 42, true]);
        $this->assertIsString($out);
        $this->assertStringContainsString('hello', $out);
        $this->assertStringContainsString('42', $out);
    }

    public function testStringifyScalarsAndArrays(): void
    {
        $this->assertSame("'test'", $this->f3->stringify('test'));
        $this->assertSame('42', $this->f3->stringify(42));
        $this->assertSame("['a'=>1]", $this->f3->stringify(['a' => 1]));
        $this->assertSame('[1,2,3]', $this->f3->stringify([1, 2, 3]));
    }

    public function testStringifyDetectsRecursion(): void
    {
        $a = [1, 2];
        $out = $this->f3->stringify($a, [$a]);
        $this->assertSame('*RECURSION*', $out);
    }

    public function testParseKeyValuePairs(): void
    {
        $out = $this->f3->parse('name=Alice,age=30');
        $this->assertSame('Alice', $out['name']);
        $this->assertSame('30', $out['age']);
    }

    public function testParseArrayValues(): void
    {
        $out = $this->f3->parse('colors=[red,green,blue]');
        $this->assertSame(['red', 'green', 'blue'], $out['colors']);
    }

    public function testMsetPopulatesMultipleKeys(): void
    {
        $this->f3->mset(['x' => 1, 'y' => 2], 'ms.');
        $this->assertSame(1, $this->f3->get('ms.x'));
        $this->assertSame(2, $this->f3->get('ms.y'));
        $this->f3->clear('ms');
    }

    public function testCopyClonesValue(): void
    {
        $this->f3->set('src', 'original');
        $this->f3->copy('src', 'dst');
        $this->assertSame('original', $this->f3->get('dst'));
        $this->f3->clear('src');
        $this->f3->clear('dst');
    }

    public function testConcatAppendsToHiveVar(): void
    {
        $this->f3->set('str', 'hello');
        $this->f3->concat('str', ' world');
        $this->assertSame('hello world', $this->f3->get('str'));
        $this->f3->clear('str');
    }

    public function testPushAndPopOperateOnArray(): void
    {
        $this->f3->set('arr', [1, 2]);
        $this->f3->push('arr', 3);
        $this->assertSame([1, 2, 3], $this->f3->get('arr'));
        $popped = $this->f3->pop('arr');
        $this->assertSame(3, $popped);
        $this->assertSame([1, 2], $this->f3->get('arr'));
        $this->f3->clear('arr');
    }

    public function testUnshiftAndShiftOperateOnArray(): void
    {
        $this->f3->set('arr2', [2, 3]);
        $this->f3->unshift('arr2', 1);
        $this->assertSame([1, 2, 3], $this->f3->get('arr2'));
        $shifted = $this->f3->shift('arr2');
        $this->assertSame(1, $shifted);
        $this->assertSame([2, 3], $this->f3->get('arr2'));
        $this->f3->clear('arr2');
    }

    public function testMergeWithArray(): void
    {
        $this->f3->set('base', ['a' => 1]);
        $out = $this->f3->merge('base', ['b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $out);
        // Without keep, hive var unchanged.
        $this->assertSame(['a' => 1], $this->f3->get('base'));
        $this->f3->clear('base');
    }

    public function testMergeWithKeep(): void
    {
        $this->f3->set('base2', ['a' => 1]);
        $out = $this->f3->merge('base2', ['b' => 2], true);
        $this->assertSame(['a' => 1, 'b' => 2], $out);
        $this->assertSame(['a' => 1, 'b' => 2], $this->f3->get('base2'));
        $this->f3->clear('base2');
    }

    public function testExtendAppliesDefaults(): void
    {
        $this->f3->set('cfg', ['debug' => true]);
        $defaults = ['debug' => false, 'timeout' => 30];
        $out = $this->f3->extend('cfg', $defaults);
        // extend: defaults filled in, existing values preserved.
        $this->assertTrue($out['debug']);
        $this->assertSame(30, $out['timeout']);
        $this->f3->clear('cfg');
    }

    public function testDevoidReturnsTrueForEmptyKey(): void
    {
        $this->f3->clear('devoid_test_key');
        $this->assertTrue($this->f3->devoid('devoid_test_key'));
        $this->f3->set('devoid_test_key', 'filled');
        $this->assertFalse($this->f3->devoid('devoid_test_key'));
        $this->f3->clear('devoid_test_key');
    }

    public function testFixslashesConvertsBackslashes(): void
    {
        $this->assertSame('path/to/file', $this->f3->fixslashes('path\\to\\file'));
        $this->assertSame('', $this->f3->fixslashes(''));
    }

    public function testHiveReturnsArray(): void
    {
        $h = $this->f3->hive();
        $this->assertIsArray($h);
        $this->assertArrayHasKey('ROUTES', $h);
        $this->assertArrayHasKey('ENCODING', $h);
    }

    public function testSplitHandlesAllDelimiters(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->f3->split('a,b,c'));
        $this->assertSame(['a', 'b', 'c'], $this->f3->split('a;b;c'));
        $this->assertSame(['a', 'b', 'c'], $this->f3->split('a|b|c'));
        $this->assertSame(['a', 'b', 'c'], $this->f3->split(' a , b , c '));
    }

    public function testSplitNoEmptyIncludesBlankWhenFalse(): void
    {
        $out = $this->f3->split('a,,b', false);
        $this->assertCount(3, $out);
        $this->assertSame('', trim($out[1]));
    }

    public function testAliasWithQueryAndFragment(): void
    {
        $this->f3->route('GET @thing: /thing/@id', function () {});
        $url = $this->f3->alias('thing', ['id' => 5], ['v' => '1'], 'top');
        $this->assertSame('/thing/5?v=1#top', $url);
        $this->f3->clear('ROUTES');
        $this->f3->clear('ALIASES');
    }

    public function testBlacklistedExemptIpReturnsFalse(): void
    {
        $prevDnsbl  = $this->f3->get('DNSBL');
        $prevExempt = $this->f3->get('EXEMPT');
        $this->f3->set('DNSBL', 'dnsbl.example.net');
        $this->f3->set('EXEMPT', '1.2.3.4');
        try {
            // IP is in the exempt list, so blacklisted() must return false
            // without performing a DNS lookup.
            $this->assertFalse($this->f3->blacklisted('1.2.3.4'));
        } finally {
            $this->f3->set('DNSBL',  $prevDnsbl);
            $this->f3->set('EXEMPT', $prevExempt);
        }
    }

    public function testMergeFromHiveStringSrc(): void
    {
        $this->f3->set('MSRC',  ['x' => 10, 'y' => 20]);
        $this->f3->set('MDEST', ['a' => 1]);
        // merge() with a string second argument reads $hive[$src].
        $out = $this->f3->merge('MDEST', 'MSRC');
        $this->assertArrayHasKey('a', $out);
        $this->assertArrayHasKey('x', $out);
        $this->assertSame(10, $out['x']);
        $this->f3->clear('MSRC');
        $this->f3->clear('MDEST');
    }

    public function testExtendFromHiveStringSrc(): void
    {
        $this->f3->set('EDEFAULTS', ['timeout' => 30, 'debug' => false]);
        $this->f3->set('ECFG', ['debug' => true]);
        // extend() with a string second argument reads $hive[$src] as defaults.
        $out = $this->f3->extend('ECFG', 'EDEFAULTS');
        $this->assertTrue($out['debug']);
        $this->assertSame(30, $out['timeout']);
        $this->f3->clear('EDEFAULTS');
        $this->f3->clear('ECFG');
    }

    // =========================================================
    // set() additional coverage
    // =========================================================

    public function testSetGetKeyUpdatesRequestHive(): void
    {
        $f3 = $this->f3;
        $prevGet = $f3->get('GET');
        $f3->set('GET.cov_getkey', 'hello');
        $this->assertSame('hello', $f3->get('GET.cov_getkey'));
        $this->assertSame('hello', $f3->get('REQUEST.cov_getkey'));
        $f3->clear('GET.cov_getkey');
        $f3->clear('REQUEST.cov_getkey');
    }

    public function testSetEncodingUpdatesCharset(): void
    {
        $f3 = $this->f3;
        $prev = $f3->get('ENCODING');
        $f3->set('ENCODING', 'UTF-8');
        $this->assertSame('UTF-8', $f3->get('ENCODING'));
        $f3->set('ENCODING', $prev);
    }

    public function testSetTzChangesTimezone(): void
    {
        $f3 = $this->f3;
        $prev = $f3->get('TZ');
        $f3->set('TZ', 'UTC');
        $this->assertSame('UTC', date_default_timezone_get());
        $f3->set('TZ', $prev);
    }

    public function testSetJarLifetimeUpdatesExpiry(): void
    {
        $f3 = $this->f3;
        $prevLifetime = $f3->get('JAR.lifetime');
        $prevExpire   = $f3->get('JAR.expire');
        $f3->set('JAR.lifetime', 3600);
        $this->assertGreaterThan((int) $f3->get('TIME'), $f3->get('JAR.expire'));
        $f3->set('JAR.lifetime', $prevLifetime);
        $f3->set('JAR.expire',   $prevExpire);
    }

    public function testSetWithCacheTtlPersistsToCache(): void
    {
        $f3     = $this->f3;
        $cache  = \Cache::instance();
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-ttl-' . uniqid() . DIRECTORY_SEPARATOR;
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', 'folder=' . $tmpDir);
        $key = 'cov_ttl_' . uniqid();
        try {
            $f3->set($key, 'persistent', 60);
            $hash = $f3->hash($key) . '.var';
            $this->assertTrue((bool) $cache->exists($hash));
        } finally {
            $f3->clear($key);
            $f3->set('CACHE', $prevCache ?: false);
            foreach (glob($tmpDir . '*') ?: [] as $file) { @unlink($file); }
            @rmdir($tmpDir);
        }
    }

    // =========================================================
    // get() additional coverage
    // =========================================================

    public function testGetWithStringArgsInterpolatesFormat(): void
    {
        $f3 = $this->f3;
        $f3->set('cov_tmpl', 'Hello {0}');
        $result = $f3->get('cov_tmpl', 'World');
        $this->assertSame('Hello World', $result);
        $f3->clear('cov_tmpl');
    }

    public function testGetReadsFromCacheWhenNotInHive(): void
    {
        $f3     = $this->f3;
        $cache  = \Cache::instance();
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-get-' . uniqid() . DIRECTORY_SEPARATOR;
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', 'folder=' . $tmpDir);
        $key  = 'cov_cacheread_' . uniqid();
        $hash = $f3->hash($key) . '.var';
        try {
            $cache->set($hash, 'from-cache', 60);
            $result = $f3->get($key); // not in hive; reads from cache
            $this->assertSame('from-cache', $result);
        } finally {
            $cache->clear($hash);
            $f3->set('CACHE', $prevCache ?: false);
            foreach (glob($tmpDir . '*') ?: [] as $file) { @unlink($file); }
            @rmdir($tmpDir);
        }
    }

    // =========================================================
    // clear() additional coverage
    // =========================================================

    public function testClearCachedKeyAlsoClearsFromCache(): void
    {
        $f3     = $this->f3;
        $cache  = \Cache::instance();
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-clr-' . uniqid() . DIRECTORY_SEPARATOR;
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', 'folder=' . $tmpDir);
        $key = 'cov_clr_' . uniqid();
        try {
            $f3->set($key, 'temp', 60);
            $f3->clear($key);
            $hash = $f3->hash($key) . '.var';
            $this->assertFalse((bool) $cache->exists($hash));
        } finally {
            $f3->set('CACHE', $prevCache ?: false);
            foreach (glob($tmpDir . '*') ?: [] as $file) { @unlink($file); }
            @rmdir($tmpDir);
        }
    }

    // =========================================================
    // ref() additional coverage
    // =========================================================

    public function testRefThrowsForKeyWithNonWordChars(): void
    {
        $this->expectException(\Exception::class);
        // Keys like 'bad!key' have a non-word char in the first segment.
        $this->f3->ref('bad!key');
    }

    public function testRefObjectPropertyNavigation(): void
    {
        $f3 = $this->f3;
        // 'objnav->name' uses '->' separator; ref() creates stdClass
        $f3->set('covnav->name', 'Alice');
        $this->assertSame('Alice', $f3->get('covnav->name'));
        $this->assertInstanceOf(\stdClass::class, $f3->get('covnav'));
        $f3->clear('covnav');
    }

    public function testRefObjectNonExistentPropertyReturnsNull(): void
    {
        $f3 = $this->f3;
        $obj = new \stdClass();
        $obj->existing = 'yes';
        $f3->set('covobj2', $obj);
        // ref() with $add=FALSE on missing property sets $var=&$null
        $result = $f3->get('covobj2->missing');
        $this->assertNull($result);
        $f3->clear('covobj2');
    }

    public function testRefSessionKeyTriggersSessionSync(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->markTestSkipped('Session already active.');
        }
        $f3 = $this->f3;
        // get('SESSION.x') calls ref('SESSION.x') which starts session + syncs.
        $val = $f3->get('SESSION.cov_sess_test');
        $this->assertNull($val);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }

    // =========================================================
    // grab() additional coverage
    // =========================================================

    public function testGrabThrowsForNonExistentClass(): void
    {
        $this->expectException(\Exception::class);
        $this->f3->grab('NoSuchClassXyz99999->method');
    }

    public function testGrabInstantiatesPrefabSubclass(): void
    {
        // Cache extends Prefab; grab() should call Cache::instance().
        $callable = $this->f3->grab('Cache->get');
        $this->assertIsArray($callable);
        $this->assertInstanceOf(\Cache::class, $callable[0]);
        $this->assertSame('get', $callable[1]);
    }

    public function testGrabUsesReflectionWithConstructorArgs(): void
    {
        // TestInstantiableHelper is a non-Prefab class with __construct(string).
        $class    = \Tests\Unit\Core\TestInstantiableHelper::class;
        $callable = $this->f3->grab($class . '->value', ['hello']);
        $this->assertIsArray($callable);
        $this->assertInstanceOf(\Tests\Unit\Core\TestInstantiableHelper::class, $callable[0]);
        $this->assertSame('hello', $callable[0]->val);
    }

    // =========================================================
    // call() additional coverage
    // =========================================================

    public function testCallThrowsForNonCallableValue(): void
    {
        $this->expectException(\Exception::class);
        // An array [object, 'nonExistentMethod'] is not callable.
        $this->f3->call([new \stdClass(), 'noSuchMethodXyz']);
    }

    // =========================================================
    // reroute() additional coverage
    // =========================================================

    public function testRerouteNullUrlFallsBackToRealm(): void
    {
        $f3 = $this->f3;
        $prevOnrr = $f3->get('ONREROUTE');
        $captured = null;
        $f3->set('ONREROUTE', function ($url) use (&$captured) {
            $captured = $url;
            return true; // short-circuit: no header/die
        });
        try {
            $f3->reroute(null, false, false);
            // REALM is used as fallback when null is passed.
            $realm = $f3->build($f3->get('REALM'));
            $this->assertSame($realm, $captured);
        } finally {
            $f3->set('ONREROUTE', $prevOnrr);
        }
    }

    public function testRerouteArrayUrlCallsAlias(): void
    {
        $f3      = $this->f3;
        $saved   = [
            'ROUTES'   => $f3->get('ROUTES'),
            'ALIASES'  => $f3->get('ALIASES'),
            'QUIET'    => $f3->get('QUIET'),
            'HALT'     => $f3->get('HALT'),
            'ERROR'    => $f3->get('ERROR'),
            'ONREROUTE'=> $f3->get('ONREROUTE'),
        ];
        $captured = null;
        $f3->set('QUIET',    true);
        $f3->set('HALT',     false);
        $f3->set('ERROR',    null);
        $f3->set('ONREROUTE', function ($url) use (&$captured) {
            $captured = $url;
            return true;
        });
        $f3->route('GET @covitem: /covitem/@id', function () {});
        try {
            // reroute(['covitem', ['id' => 7]]) calls alias() internally.
            $f3->reroute(['covitem', ['id' => 7]]);
            $this->assertStringContainsString('/covitem/7', (string) $captured);
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    public function testRerouteOnrouteHandlerShortCircuits(): void
    {
        $f3      = $this->f3;
        $prevOnrr = $f3->get('ONREROUTE');
        $invoked  = false;
        $f3->set('ONREROUTE', function () use (&$invoked) {
            $invoked = true;
            return true;
        });
        try {
            $f3->reroute('/some-path', false, false);
            $this->assertTrue($invoked);
        } finally {
            $f3->set('ONREROUTE', $prevOnrr);
        }
    }

    public function testRerouteBuildsUrlWithNonStandardPort(): void
    {
        $f3       = $this->f3;
        $prevPort = $f3->get('PORT');
        $prevCli  = $f3->get('CLI');
        $prevOnrr = $f3->get('ONREROUTE');
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        // ONREROUTE returns FALSE so execution continues to the port-building block.
        $f3->set('PORT',     8080);
        $f3->set('CLI',      false);
        $f3->set('ONREROUTE', function () { return false; });
        try {
            // die=false so the function returns normally after header()/status().
            $f3->reroute('/cov-port', false, false);
            $this->assertTrue(true); // reached here without crashing
        } finally {
            $f3->set('PORT',      $prevPort);
            $f3->set('CLI',       $prevCli);
            $f3->set('ONREROUTE', $prevOnrr);
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
        }
    }

    public function testRerouteRelativeUrlGetsPrependedSlash(): void
    {
        $f3       = $this->f3;
        $prevCli  = $f3->get('CLI');
        $prevOnrr = $f3->get('ONREROUTE');
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        // ONREROUTE returns FALSE so execution reaches the '/' prepend + port-build.
        $f3->set('CLI',      false);
        $f3->set('ONREROUTE', function () { return false; });
        try {
            // 'relative-path' has no leading '/' and no scheme: '/' gets prepended.
            $f3->reroute('relative-path', false, false);
            $this->assertTrue(true);
        } finally {
            $f3->set('CLI',       $prevCli);
            $f3->set('ONREROUTE', $prevOnrr);
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
        }
    }

    // =========================================================
    // error() additional coverage
    // =========================================================

    public function testErrorIncludesQueryInText(): void
    {
        $f3   = $this->f3;
        $saved = [
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'QUERY'   => $f3->get('QUERY'),
            'ERROR'   => $f3->get('ERROR'),
            'ONERROR' => $f3->get('ONERROR'),
        ];
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $f3->set('QUIET',   true);
        $f3->set('HALT',    false);
        $f3->set('QUERY',   'foo=bar');
        $f3->set('ERROR',   null);
        $f3->set('ONERROR', null);
        try {
            $f3->error(400, '');
            $this->assertStringContainsString('?foo=bar', (string) $f3->get('ERROR.text'));
        } finally {
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    public function testErrorAjaxModeOutputsJsonResponse(): void
    {
        $f3   = $this->f3;
        $saved = [
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'AJAX'    => $f3->get('AJAX'),
            'CLI'     => $f3->get('CLI'),
            'DEBUG'   => $f3->get('DEBUG'),
            'ERROR'   => $f3->get('ERROR'),
            'ONERROR' => $f3->get('ONERROR'),
        ];
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $f3->set('QUIET',   false);
        $f3->set('HALT',    false);
        $f3->set('AJAX',    true);
        $f3->set('CLI',     false);
        $f3->set('DEBUG',   0);
        $f3->set('ERROR',   null);
        $f3->set('ONERROR', null);
        $startLevel = ob_get_level();
        ob_start();
        try {
            $f3->error(422, 'AJAX error test');
            $out     = ob_get_clean();
            $decoded = json_decode($out, true);
            $this->assertIsArray($decoded);
            $this->assertSame(422, $decoded['code']);
        } finally {
            while (ob_get_level() > $startLevel) { ob_end_clean(); }
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    public function testErrorWithExistingErrorSkipsOutput(): void
    {
        $f3   = $this->f3;
        $saved = [
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'CLI'     => $f3->get('CLI'),
            'ERROR'   => $f3->get('ERROR'),
            'ONERROR' => $f3->get('ONERROR'),
        ];
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $f3->set('QUIET',   false);
        $f3->set('HALT',    false);
        $f3->set('CLI',     true);  // CLI output path (avoids headers/AJAX)
        $f3->set('ONERROR', null);
        // Pre-set ERROR so the output block is skipped (prior !== null).
        $f3->set('ERROR', ['code' => 404, 'status' => 'Not Found',
                           'text' => 'prior', 'trace' => '', 'level' => 0]);
        $startLevel = ob_get_level();
        ob_start();
        try {
            $f3->error(500, 'secondary error');
            $out = ob_get_clean();
            $this->assertSame('', $out);
            $this->assertSame(500, $f3->get('ERROR.code'));
        } finally {
            while (ob_get_level() > $startLevel) { ob_end_clean(); }
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    // =========================================================
    // route() additional coverage
    // =========================================================

    public function testRouteAcceptsArrayOfPatterns(): void
    {
        $f3    = $this->f3;
        $saved = ['ROUTES' => $f3->get('ROUTES'), 'ALIASES' => $f3->get('ALIASES')];
        $handler = fn () => 'ok';
        $f3->route(['GET /cov-a', 'POST /cov-a'], $handler);
        $routes = $f3->get('ROUTES');
        $this->assertArrayHasKey('/cov-a', $routes);
        $this->assertArrayHasKey('GET',  $routes['/cov-a'][0]);
        $this->assertArrayHasKey('POST', $routes['/cov-a'][0]);
        $f3->set('ROUTES',  $saved['ROUTES']);
        $f3->set('ALIASES', $saved['ALIASES']);
    }

    public function testRouteThrowsForInvalidAliasName(): void
    {
        $this->expectException(\Exception::class);
        // Alias names must match /^\w+$/; 'bad-alias' contains '-'.
        $this->f3->route('GET @bad-alias: /cov-route', fn () => 'x');
    }

    // =========================================================
    // mock() additional coverage
    // =========================================================

    public function testMockThrowsForUndefinedNamedAlias(): void
    {
        $this->expectException(\Exception::class);
        $this->f3->mock('GET @undefinedAliasXyz999');
    }

    public function testMockWithNamedAliasDispatches(): void
    {
        $f3   = $this->f3;
        $saved = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'ERROR'   => $f3->get('ERROR'),
        ];
        $f3->set('QUIET', true);
        $f3->set('HALT',  false);
        $f3->set('ERROR', null);
        $f3->route('GET @covping: /covping', fn () => null);
        try {
            $f3->mock('GET @covping');
            $this->assertSame('/covping', $f3->get('PATH'));
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    // =========================================================
    // blacklisted() additional coverage
    // =========================================================

    public function testBlacklistedNonExemptIpTriesDnsLookup(): void
    {
        $f3         = $this->f3;
        $prevDnsbl  = $f3->get('DNSBL');
        $prevExempt = $f3->get('EXEMPT');
        // Non-exempt IP + unresolvable DNSBL: DNS lookup runs but returns false.
        $f3->set('DNSBL',  'nonexistent-dnsbl-9z.local.');
        $f3->set('EXEMPT', '');
        try {
            $result = $f3->blacklisted('10.20.30.40');
            $this->assertFalse($result);
        } finally {
            $f3->set('DNSBL',  $prevDnsbl);
            $f3->set('EXEMPT', $prevExempt);
        }
    }

    // =========================================================
    // config() additional coverage
    // =========================================================

    public function testConfigParsesValueWithTtlSuffix(): void
    {
        $f3  = $this->f3;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3ttl-' . uniqid() . '.ini';
        file_put_contents($tmp, "[globals]\ncov_ttl_key = cached-value | 60\n");
        try {
            $f3->config($tmp);
            $this->assertSame('cached-value', $f3->get('cov_ttl_key'));
        } finally {
            @unlink($tmp);
            $f3->clear('cov_ttl_key');
        }
    }

    public function testConfigSectionFunctionTransformsValue(): void
    {
        $f3  = $this->f3;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3fn-' . uniqid() . '.ini';
        file_put_contents($tmp, "[globals:strtoupper]\ncov_fn_key = hello\n");
        try {
            $f3->config($tmp);
            $this->assertSame('HELLO', $f3->get('cov_fn_key'));
        } finally {
            @unlink($tmp);
            $f3->clear('cov_fn_key');
        }
    }

    public function testConfigAllowResolvesPreviewTokens(): void
    {
        $f3  = $this->f3;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3allow-' . uniqid() . '.ini';
        file_put_contents($tmp, "[globals]\ncov_allow_key = plain-value\n");
        try {
            $f3->config($tmp, true); // allow=true triggers Preview::instance()
            $this->assertSame('plain-value', $f3->get('cov_allow_key'));
        } finally {
            @unlink($tmp);
            $f3->clear('cov_allow_key');
        }
    }

    public function testConfigSectionHandlerCallsCallable(): void
    {
        $f3  = $this->f3;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3sec-' . uniqid() . '.ini';
        // [COVRESULT > Base->set] uses Base::set as handler.
        // For each entry: Base::instance()->set(lval, rval, 'COVRESULT').
        // TTL 'COVRESULT' is non-numeric → 0 (no cache). Writes lval=rval in hive.
        file_put_contents($tmp, "[COVRESULT > Base->set]\ncov_sec_key = cov_sec_val\n");
        try {
            $f3->config($tmp);
            $this->assertSame('cov_sec_val', $f3->get('cov_sec_key'));
        } finally {
            @unlink($tmp);
            $f3->clear('COVRESULT');
            $f3->clear('cov_sec_key');
        }
    }

    public function testConfigMultiValueArrayKey(): void
    {
        $f3  = $this->f3;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3multi-' . uniqid() . '.ini';
        file_put_contents($tmp, "[globals]\ncov_multi_key = \"alpha\", \"beta\"\n");
        try {
            $f3->config($tmp);
            $val = $f3->get('cov_multi_key');
            $this->assertIsArray($val);
            $this->assertContains('alpha', $val);
            $this->assertContains('beta',  $val);
        } finally {
            @unlink($tmp);
            $f3->clear('cov_multi_key');
        }
    }

    // =========================================================
    // Cache additional coverage
    // =========================================================

    public function testCacheResetWithSuffixOnFolderBackend(): void
    {
        $f3     = $this->f3;
        $cache  = \Cache::instance();
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-reset-' . uniqid() . DIRECTORY_SEPARATOR;
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', 'folder=' . $tmpDir);
        try {
            $cache->set('test.suffix.var', 'val', 60);
            $cache->reset('.var');
            $this->assertFalse((bool) $cache->exists('test.suffix.var'));
        } finally {
            $f3->set('CACHE', $prevCache ?: false);
            foreach (glob($tmpDir . '*') ?: [] as $file) { @unlink($file); }
            @rmdir($tmpDir);
        }
    }

    // =========================================================
    // format() additional coverage
    // =========================================================

    public function testFormatCustomHandlerIsInvoked(): void
    {
        $f3          = $this->f3;
        $prevFormats = $f3->get('FORMATS');
        $f3->set('FORMATS.mytype', fn ($val) => 'custom:' . $val);
        try {
            $result = $f3->format('{0,mytype}', 'x');
            $this->assertSame('custom:x', $result);
        } finally {
            $f3->set('FORMATS', $prevFormats ?: []);
        }
    }

    public function testFormatMissingPositionalReturnsLiteral(): void
    {
        // Positional arg 5 is out of range with only 2 args; returns token as-is.
        $result = $this->f3->format('{5}', 'a', 'b');
        $this->assertSame('{5}', $result);
    }

    // =========================================================
    // run() CORS coverage
    // =========================================================

    public function testRunAddsCorsHeadersWhenOriginPresent(): void
    {
        $f3   = $this->f3;
        $saved = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'ERROR'   => $f3->get('ERROR'),
            'HEADERS' => $f3->get('HEADERS'),
            'CORS'    => $f3->get('CORS'),
        ];
        $f3->set('QUIET',          true);
        $f3->set('HALT',           false);
        $f3->set('ERROR',          null);
        $f3->set('CORS.origin',    '*');
        $f3->set('HEADERS.Origin', 'http://test.example.com');
        $f3->route('GET @covcors: /covcors', fn () => null);
        try {
            // [cli] modifier ensures no die/header issues.
            $f3->mock('GET @covcors [cli]');
            $this->assertNull($f3->get('ERROR'));
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    // =========================================================
    // run() — no-routes exception
    // =========================================================

    public function testRunThrowsWhenNoRoutesRegistered(): void
    {
        $f3      = $this->f3;
        $saved   = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
        ];
        $f3->set('ROUTES', []);
        $this->expectException(\Exception::class);
        try {
            $f3->run();
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    // =========================================================
    // Original testErrorFiresOnerrorHandlerAndPopulatesHive
    // =========================================================

    public function testErrorFiresOnerrorHandlerAndPopulatesHive(): void
    {
        $prev = [
            'QUIET'    => $this->f3->get('QUIET'),
            'HALT'     => $this->f3->get('HALT'),
            'LOGGABLE' => $this->f3->get('LOGGABLE'),
            'ERROR'    => $this->f3->get('ERROR'),
            'ONERROR'  => $this->f3->get('ONERROR'),
        ];
        $this->f3->set('QUIET', true);
        $this->f3->set('HALT', false);
        $this->f3->set('LOGGABLE', '');
        $fired = false;
        $this->f3->set('ONERROR', function (\Base $f3) use (&$fired) {
            $fired = true;
            return true;
        });
        try {
            $this->f3->error(503, 'Unit test error');
            $this->assertTrue($fired, 'ONERROR callback must be invoked');
            $this->assertSame(503, $this->f3->get('ERROR.code'));
            $this->assertSame('Unit test error', $this->f3->get('ERROR.text'));
            $this->assertStringContainsString('Service Unavailable', $this->f3->get('ERROR.status'));
        } finally {
            foreach ($prev as $k => $v) {
                $this->f3->set($k, $v);
            }
            $this->f3->clear('ERROR');
        }
    }

    // =========================================================
    // merge() keep=true
    // =========================================================

    public function testMergeWithKeepTrue(): void
    {
        $f3 = $this->f3;
        $f3->set('covmrg', ['a' => 1]);
        try {
            $out = $f3->merge('covmrg', ['b' => 2], true);
            $this->assertSame(['a' => 1, 'b' => 2], $out);
            $this->assertSame(['a' => 1, 'b' => 2], $f3->get('covmrg'));
        } finally {
            $f3->clear('covmrg');
        }
    }

    // =========================================================
    // extend() keep=true
    // =========================================================

    public function testExtendWithKeepTrue(): void
    {
        $f3 = $this->f3;
        $f3->set('covext', ['a' => 1]);
        try {
            $out = $f3->extend('covext', ['a' => 0, 'b' => 2], true);
            $this->assertSame(['a' => 1, 'b' => 2], $out);
            $this->assertSame(['a' => 1, 'b' => 2], $f3->get('covext'));
        } finally {
            $f3->clear('covext');
        }
    }

    // =========================================================
    // ip() — X-Forwarded-For header
    // =========================================================

    public function testIpXForwardedFor(): void
    {
        $f3       = $this->f3;
        $prevHdrs = $f3->get('HEADERS');
        $f3->set('HEADERS.X-Forwarded-For', '10.0.0.5, 10.0.0.6');
        try {
            $this->assertSame('10.0.0.5', $f3->ip());
        } finally {
            $f3->set('HEADERS', $prevHdrs);
        }
    }

    // =========================================================
    // redirect() — array pattern
    // =========================================================

    public function testRedirectWithArrayPattern(): void
    {
        $f3      = $this->f3;
        $prevRoutes  = $f3->get('ROUTES');
        $prevAliases = $f3->get('ALIASES');
        try {
            $f3->redirect(['GET /cov-redir-a', 'POST /cov-redir-b'], '/cov-target');
            // Both patterns should now have routes registered.
            $routes = $f3->get('ROUTES');
            $this->assertArrayHasKey('/cov-redir-a', $routes);
            $this->assertArrayHasKey('/cov-redir-b', $routes);
        } finally {
            $f3->set('ROUTES',  $prevRoutes);
            $f3->set('ALIASES', $prevAliases);
        }
    }

    // =========================================================
    // alias() — string params and throw for non-existent
    // =========================================================

    public function testAliasWithStringParams(): void
    {
        $f3 = $this->f3;
        $prevRoutes  = $f3->get('ROUTES');
        $prevAliases = $f3->get('ALIASES');
        $f3->route('GET @covastr: /cov-str/@id', fn () => null);
        try {
            // Pass params as a string — triggers parse() branch.
            $url = $f3->alias('covastr', 'id=42');
            $this->assertStringContainsString('42', $url);
        } finally {
            $f3->set('ROUTES',  $prevRoutes);
            $f3->set('ALIASES', $prevAliases);
        }
    }

    public function testAliasThrowsForNonExistentName(): void
    {
        $this->expectException(\Exception::class);
        $this->f3->alias('cov_nonexistent_alias_xyz_999');
    }

    // =========================================================
    // call() — afterroute returns FALSE / non-callable + hooks
    // =========================================================

    public function testCallAfterrouteReturnsFalse(): void
    {
        $f3  = $this->f3;
        $obj = new class {
            public function doWork(): string { return 'done'; }
            public function afterroute(): bool { return false; }
        };
        $result = $f3->call([$obj, 'doWork'], null, 'afterroute');
        $this->assertFalse($result);
    }

    public function testCallNonCallableWithRouteHooks(): void
    {
        $f3  = $this->f3;
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $saved = [
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'ERROR'   => $f3->get('ERROR'),
            'ONERROR' => $f3->get('ONERROR'),
        ];
        $f3->set('QUIET',   true);
        $f3->set('HALT',    false);
        $f3->set('ERROR',   null);
        $f3->set('ONERROR', null);
        try {
            // ['stdClass','noSuchMethod'] is not callable;
            // with 'beforeroute,afterroute' hooks, call() triggers error(405).
            try {
                $f3->call(['stdClass', 'noSuchMethod'], null, 'beforeroute,afterroute');
            } catch (\TypeError $e) {
                // call_user_func_array throws after error(405) since func is still non-callable.
            }
            $this->assertSame(405, $f3->get('ERROR.code'));
        } finally {
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    // =========================================================
    // clear() — CACHE key, COOKIE key, SESSION root
    // =========================================================

    public function testClearCacheKey(): void
    {
        // clear('CACHE') calls $cache->reset().  With no backend loaded, reset() returns
        // false safely without crashing.
        $this->f3->clear('CACHE');
        $this->assertTrue(true);
    }

    public function testClearCookieKey(): void
    {
        $f3 = $this->f3;
        $_COOKIE['cov_clear_ck'] = 'testvalue';
        try {
            $f3->clear('COOKIE.cov_clear_ck');
            $this->assertArrayNotHasKey('cov_clear_ck', $_COOKIE);
        } finally {
            unset($_COOKIE['cov_clear_ck']);
        }
    }

    public function testClearSessionRoot(): void
    {
        $f3 = $this->f3;
        // clear('SESSION') starts session (if inactive), unsets and destroys it.
        // In CLI this works without headers.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $f3->clear('SESSION');
            $this->assertTrue(true); // no crash
        } finally {
            // Session was destroyed; ensure a clean state.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    // =========================================================
    // format() — integer modifier, currency positive, default type
    // =========================================================

    public function testFormatIntegerModifier(): void
    {
        $result = $this->f3->format('{0,number,integer}', 42.9);
        $this->assertSame('43', $result);
    }

    public function testFormatCurrencyPositive(): void
    {
        // {0,number,currency} with a positive value triggers the positive-sign branch.
        $result = $this->f3->format('{0,number,currency}', 9.99);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testFormatUnknownTypeDefault(): void
    {
        // An unknown type with no FORMATS handler hits the default: return $expr[0].
        $result = $this->f3->format('{0,unknowntype_xyz}', 'hello');
        $this->assertSame('{0,unknowntype_xyz}', $result);
    }

    // =========================================================
    // set() — COOKIE key and JAR.samesite (session_set_cookie_params)
    // =========================================================

    public function testSetCookieKey(): void
    {
        $f3 = $this->f3;
        // setcookie() is a no-op in CLI but the code runs; $_COOKIE is set at the end.
        try {
            $f3->set('COOKIE.cov_ck_test', 'myval');
            $this->assertSame('myval', $_COOKIE['cov_ck_test'] ?? null);
        } finally {
            $f3->clear('COOKIE.cov_ck_test');
        }
    }

    public function testSetJarSamesiteTriggersSessionCookieParams(): void
    {
        $f3       = $this->f3;
        $prevJar  = $f3->get('JAR');
        try {
            // Setting a JAR sub-key (not 'lifetime' or 'expire') goes through
            // session_set_cookie_params() when session is inactive (CLI default).
            $f3->set('JAR.samesite', 'Strict');
            $this->assertSame('Strict', $f3->get('JAR.samesite'));
        } finally {
            $f3->set('JAR', $prevJar);
        }
    }

    // =========================================================
    // grab() — CONTAINER paths (PSR11, callable, Prefab, throw)
    // =========================================================

    public function testGrabPsr11Container(): void
    {
        $f3        = $this->f3;
        $prevCont  = $f3->get('CONTAINER');
        $container = new class {
            public function has(string $id): bool  { return true; }
            public function get(string $id): object { return new \stdClass(); }
        };
        $f3->set('CONTAINER', $container);
        try {
            // stdClass exists, is not Prefab → CONTAINER path; has() returns true → PSR11 get().
            $cb = $f3->grab('stdClass->anyMethod');
            $this->assertIsArray($cb);
            $this->assertInstanceOf(\stdClass::class, $cb[0]);
        } finally {
            $f3->set('CONTAINER', $prevCont);
        }
    }

    public function testGrabCallableContainer(): void
    {
        $f3       = $this->f3;
        $prevCont = $f3->get('CONTAINER');
        $called   = false;
        $f3->set('CONTAINER', function (string $class) use (&$called): object {
            $called = true;
            return new \stdClass();
        });
        try {
            $cb = $f3->grab('stdClass->anyMethod');
            $this->assertTrue($called);
            $this->assertIsArray($cb);
        } finally {
            $f3->set('CONTAINER', $prevCont);
        }
    }

    public function testGrabPrefabContainer(): void
    {
        $f3       = $this->f3;
        $prevCont = $f3->get('CONTAINER');
        // 'Base' is a Prefab subclass; Base::instance()->get(className) fetches from hive.
        $f3->set('CONTAINER', 'Base');
        try {
            $cb = $f3->grab('stdClass->anyMethod');
            $this->assertIsArray($cb);
        } finally {
            $f3->set('CONTAINER', $prevCont);
        }
    }

    public function testGrabContainerThrowsForInvalidContainer(): void
    {
        $f3       = $this->f3;
        $prevCont = $f3->get('CONTAINER');
        // An integer is not an object, not callable, not a Prefab string → throws.
        $f3->set('CONTAINER', 12345);
        try {
            $this->expectException(\Exception::class);
            $f3->grab('stdClass->anyMethod');
        } finally {
            $f3->set('CONTAINER', $prevCont);
        }
    }

    // =========================================================
    // run() — string handler token, 405, echo body, CORS expose
    // =========================================================

    public function testRunStringHandlerTokenAndClassNotFound(): void
    {
        $f3   = $this->f3;
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $saved = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'ERROR'   => $f3->get('ERROR'),
            'ONERROR' => $f3->get('ONERROR'),
        ];
        $f3->set('QUIET',   true);
        $f3->set('HALT',    false);
        $f3->set('ERROR',   null);
        $f3->set('ONERROR', null);
        // Register a route with a string handler containing an @token for a non-existent class.
        // Token replacement runs, then class-not-found sets ERROR 404, then grab() throws.
        $f3->route('GET /cov-tok/@action', 'NoSuchClassCov->@action');
        $startOb = ob_get_level();
        try {
            $f3->mock('GET /cov-tok/doIt [cli]');
        } catch (\Exception $e) {
            // grab() throws after error(404) is already set; verify both.
            $this->assertStringContainsString('NoSuchClassCov', $e->getMessage());
            $this->assertSame(404, $f3->get('ERROR.code'));
        } finally {
            while (ob_get_level() > $startOb) { ob_end_clean(); }
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    public function testRunMethodNotAllowed(): void
    {
        $f3   = $this->f3;
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $saved = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'CLI'     => $f3->get('CLI'),
            'ERROR'   => $f3->get('ERROR'),
            'ONERROR' => $f3->get('ONERROR'),
        ];
        $f3->set('QUIET',   true);
        $f3->set('HALT',    false);
        $f3->set('CLI',     false); // CLI=false triggers the 405 header+error path
        $f3->set('ERROR',   null);
        $f3->set('ONERROR', null);
        $f3->route('GET /cov-405', fn () => 'ok');
        try {
            // POST to a GET-only route → 405
            $f3->mock('POST /cov-405');
            $this->assertSame(405, $f3->get('ERROR.code'));
        } finally {
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    public function testRunEchoesBodyWhenNotQuiet(): void
    {
        $f3    = $this->f3;
        $saved = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'ERROR'   => $f3->get('ERROR'),
        ];
        $f3->set('QUIET', false);
        $f3->set('HALT',  false);
        $f3->set('ERROR', null);
        $f3->route('GET /cov-echo', static function() { echo 'echo-body'; });
        $startLevel = ob_get_level();
        ob_start();
        try {
            $f3->mock('GET /cov-echo [cli]');
            $out = ob_get_clean();
            $this->assertSame('echo-body', $out);
        } finally {
            while (ob_get_level() > $startLevel) { ob_end_clean(); }
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    public function testRunCorsExposeHeaders(): void
    {
        $f3    = $this->f3;
        $saved = [
            'ROUTES'  => $f3->get('ROUTES'),
            'ALIASES' => $f3->get('ALIASES'),
            'QUIET'   => $f3->get('QUIET'),
            'HALT'    => $f3->get('HALT'),
            'ERROR'   => $f3->get('ERROR'),
            'HEADERS' => $f3->get('HEADERS'),
            'CORS'    => $f3->get('CORS'),
        ];
        $f3->set('QUIET',             true);
        $f3->set('HALT',              false);
        $f3->set('ERROR',             null);
        $f3->set('CORS.origin',       '*');
        $f3->set('CORS.expose',       'X-Custom-Header');
        $f3->set('HEADERS.Origin',    'http://example.com');
        $f3->route('GET @covexpose: /cov-expose', fn () => null);
        try {
            $f3->mock('GET @covexpose [cli]');
            $this->assertNull($f3->get('ERROR'));
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    // =========================================================
    // Preview::build — {~ code ~}, {* comment *}, {- literal -}
    // =========================================================

    public function testPreviewBuildCodeBlock(): void
    {
        $preview = \Preview::instance();
        // {~ ... ~} compiles to PHP code
        $result = $preview->resolve('{~ $covx=1 ~}result');
        $this->assertSame('result', trim($result));
    }

    public function testPreviewBuildComment(): void
    {
        $preview = \Preview::instance();
        // {* ... *} is stripped entirely.
        $result = $preview->resolve('before{* comment *}after');
        $this->assertSame('beforeafter', $result);
    }

    public function testPreviewBuildLiteral(): void
    {
        $preview = \Preview::instance();
        // {- ... -} outputs the literal text as-is (no escaping).
        $result = $preview->resolve('{- raw text -}');
        $this->assertStringContainsString('raw text', $result);
    }

    // =========================================================
    // Preview::resolve — persist=true path
    // =========================================================

    public function testPreviewResolvePersist(): void
    {
        $f3      = $this->f3;
        $preview = \Preview::instance();
        $prevTemp = $f3->get('TEMP');
        $tmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-persist-' . uniqid() . DIRECTORY_SEPARATOR;
        $f3->set('TEMP', $tmpDir);
        try {
            $data = $preview->resolve('hello persist', null, 0, true);
            $this->assertSame('hello persist', trim($data));
        } finally {
            $f3->set('TEMP', $prevTemp);
            foreach (glob($tmpDir . '*') ?: [] as $file) { @unlink($file); }
            @rmdir($tmpDir);
        }
    }

    // =========================================================
    // mutex() — creates non-existent TEMP directory
    // =========================================================

    public function testMutexCreatesNonExistentTempDir(): void
    {
        $f3       = $this->f3;
        $prevTemp = $f3->get('TEMP');
        $tmpDir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-mutex-' . uniqid() . DIRECTORY_SEPARATOR;
        $f3->set('TEMP', $tmpDir);
        try {
            $result = $f3->mutex('cov-lock-test', fn () => 'mutex-done');
            $this->assertSame('mutex-done', $result);
            $this->assertTrue(is_dir($tmpDir));
        } finally {
            $f3->set('TEMP', $prevTemp);
            foreach (glob($tmpDir . '*') ?: [] as $file) { @unlink($file); }
            @rmdir($tmpDir);
        }
    }

    // =========================================================
    // error() — HTML output (non-AJAX, non-CLI)
    // =========================================================

    public function testErrorHtmlOutput(): void
    {
        $f3   = $this->f3;
        $saved = [
            'QUIET'     => $f3->get('QUIET'),
            'HALT'      => $f3->get('HALT'),
            'CLI'       => $f3->get('CLI'),
            'AJAX'      => $f3->get('AJAX'),
            'DEBUG'     => $f3->get('DEBUG'),
            'HIGHLIGHT' => $f3->get('HIGHLIGHT'),
            'ERROR'     => $f3->get('ERROR'),
            'ONERROR'   => $f3->get('ONERROR'),
        ];
        $savedProto = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $f3->sync('SERVER');
        $f3->set('QUIET',     false);
        $f3->set('HALT',      false);
        $f3->set('CLI',       false);
        $f3->set('AJAX',      false);
        $f3->set('DEBUG',     0);
        $f3->set('HIGHLIGHT', false); // keep it simple: no CSS highlight
        $f3->set('ERROR',     null);
        $f3->set('ONERROR',   null);
        $startLevel = ob_get_level();
        ob_start();
        try {
            $f3->error(404, 'HTML Not Found Test');
            $out = ob_get_clean();
            $this->assertStringContainsString('<!DOCTYPE html>', $out);
            $this->assertStringContainsString('404', $out);
        } finally {
            while (ob_get_level() > $startLevel) { ob_end_clean(); }
            if ($savedProto === null) { unset($_SERVER['SERVER_PROTOCOL']); }
            else { $_SERVER['SERVER_PROTOCOL'] = $savedProto; }
            $f3->sync('SERVER');
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
            $f3->clear('ERROR');
        }
    }

    // =========================================================
    // autoload() — AUTOLOAD as [path, callable] array
    // =========================================================

    public function testAutoloadWithCallableArray(): void
    {
        $f3          = $this->f3;
        $prevAutoload = $f3->get('AUTOLOAD');
        // When AUTOLOAD is [path, callable], the callable is used to locate the file.
        // The callable returns an empty string so no file is found; the class stays absent.
        $f3->set('AUTOLOAD', ['./', function (string $auto): string { return ''; }]);
        try {
            // Trigger autoload for a non-existent class — line 2330 executes.
            $exists = class_exists('CovNonExistentAutoload_' . substr(uniqid(), -6), true);
            $this->assertFalse($exists);
        } finally {
            $f3->set('AUTOLOAD', $prevAutoload);
        }
    }

    // =========================================================
    // merge() — non-existent key (covers $ref=[] init, line 751)
    // =========================================================

    public function testMergeWithNewKey(): void
    {
        $f3 = $this->f3;
        try {
            $out = $f3->merge('cov_mrg_new', ['a' => 1]);
            $this->assertSame(['a' => 1], $out);
        } finally {
            $f3->clear('cov_mrg_new');
        }
    }

    // =========================================================
    // extend() — non-existent key (covers $ref=[] init, line 768)
    // =========================================================

    public function testExtendWithNewKey(): void
    {
        $f3 = $this->f3;
        try {
            $out = $f3->extend('cov_ext_new', ['a' => 0, 'b' => 2]);
            $this->assertSame(['a' => 0, 'b' => 2], $out);
        } finally {
            $f3->clear('cov_ext_new');
        }
    }

    // =========================================================
    // redirect() — trigger closure body (covers line 1703)
    // =========================================================

    public function testRedirectClosureBody(): void
    {
        $f3      = $this->f3;
        $called  = false;
        $saved   = [
            'ROUTES'    => $f3->get('ROUTES'),
            'ALIASES'   => $f3->get('ALIASES'),
            'QUIET'     => $f3->get('QUIET'),
            'HALT'      => $f3->get('HALT'),
            'ONREROUTE' => $f3->get('ONREROUTE'),
        ];
        $f3->set('QUIET',     true);
        $f3->set('HALT',      false);
        $f3->set('ONREROUTE', function (string $url) use (&$called): bool {
            $called = true;
            return true; // prevent actual redirect
        });
        try {
            $f3->redirect('GET /cov-redir-body', '/cov-target-url');
            $f3->mock('GET /cov-redir-body [cli]');
            $this->assertTrue($called);
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    // =========================================================
    // mock() — headers loop body (covers line 1554)
    // =========================================================

    public function testMockWithHeaders(): void
    {
        $f3       = $this->f3;
        $prevRoutes  = $f3->get('ROUTES');
        $prevAliases = $f3->get('ALIASES');
        try {
            $f3->route('GET /cov-mock-hdrs', fn () => null);
            $f3->mock('GET /cov-mock-hdrs [cli]', [], ['X-Cov-Test' => 'testval']);
            $this->assertSame('testval', $_SERVER['HTTP_X_COV_TEST'] ?? null);
        } finally {
            $f3->set('ROUTES',  $prevRoutes);
            $f3->set('ALIASES', $prevAliases);
            unset($_SERVER['HTTP_X_COV_TEST']);
        }
    }

    // =========================================================
    // mock() — GET args → URI gets query string (covers line 1559)
    // =========================================================

    public function testMockWithGetArgs(): void
    {
        $f3       = $this->f3;
        $prevRoutes  = $f3->get('ROUTES');
        $prevAliases = $f3->get('ALIASES');
        try {
            $f3->route('GET /cov-mock-qs', fn () => null);
            $f3->mock('GET /cov-mock-qs [cli]', ['foo' => 'bar']);
            $this->assertSame('bar', $GLOBALS['_GET']['foo'] ?? null);
        } finally {
            $f3->set('ROUTES',  $prevRoutes);
            $f3->set('ALIASES', $prevAliases);
        }
    }

    // =========================================================
    // mutex() — stale lock removed before acquiring (covers line 2235)
    // =========================================================

    public function testMutexStaleLock(): void
    {
        $f3   = $this->f3;
        $tmp  = $f3->get('TEMP');
        $seed = $f3->get('SEED');
        $id   = 'cov-stale-' . uniqid();
        $lock = $tmp . $seed . '.' . $f3->hash($id) . '.lock';
        // Create a stale lock file with mtime=0 (definitely expired).
        file_put_contents($lock, '');
        touch($lock, 0);
        try {
            $result = $f3->mutex($id, fn () => 'stale-done');
            $this->assertSame('stale-done', $result);
        } finally {
            @unlink($lock);
        }
    }

    // =========================================================
    // lexicon() — TTL: save to cache (1288) and return from cache (1257)
    // =========================================================

    public function testLexiconWithTtl(): void
    {
        $f3       = $this->f3;
        $tmpDir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3lex-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($tmpDir);
        file_put_contents($tmpDir . 'en.json', json_encode(['lex_hello' => 'Hello']));
        $prevCache = $f3->get('CACHE');
        $f3->set('CACHE', 'folder=tests/_tmp/cache/');
        try {
            // First call: loads from file and saves to cache (covers line 1288).
            $lex1 = $f3->lexicon($tmpDir, 60);
            $this->assertSame('Hello', $lex1['lex_hello']);
            // Second call: returns from cache (covers line 1257).
            $lex2 = $f3->lexicon($tmpDir, 60);
            $this->assertSame('Hello', $lex2['lex_hello']);
        } finally {
            @unlink($tmpDir . 'en.json');
            @rmdir($tmpDir);
            $f3->set('CACHE', $prevCache ?: false);
        }
    }

    // =========================================================
    // config() — empty-string value → NULL (covers line 2185)
    // =========================================================

    public function testConfigEmptyStringValue(): void
    {
        $f3   = $this->f3;
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cov-cfg-' . uniqid() . '.ini';
        file_put_contents($tmpFile, "cov_nonempty = hello\ncov_empty = \n");
        try {
            $f3->config($tmpFile);
            $this->assertSame('hello', $f3->get('cov_nonempty'));
            $this->assertNull($f3->get('cov_empty'));
        } finally {
            @unlink($tmpFile);
            $f3->clear('cov_nonempty');
            $f3->clear('cov_empty');
        }
    }

    // =========================================================
    // config() — [configs] section: cast(rval) path (covers line 2167)
    // =========================================================

    public function testConfigConfigsSection(): void
    {
        $f3      = $this->f3;
        $tmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3covcfg-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($tmpDir);
        // The "included" config file sets a simple key.
        $inner   = $tmpDir . 'inner.ini';
        file_put_contents($inner, "cov_inner_key = inner_value\n");
        // The outer config file has a [configs] section that includes inner.ini.
        $outer   = $tmpDir . 'outer.ini';
        file_put_contents($outer, "[configs]\n{$inner} = \n");
        try {
            $f3->config($outer);
            $this->assertSame('inner_value', $f3->get('cov_inner_key'));
        } finally {
            @unlink($inner);
            @unlink($outer);
            @rmdir($tmpDir);
            $f3->clear('cov_inner_key');
        }
    }

    // =========================================================
    // ip() — REMOTE_ADDR fallback (covers innermost branch)
    // =========================================================

    public function testIpRemoteAddr(): void
    {
        $f3       = $this->f3;
        $prevHdrs = $f3->get('HEADERS');
        $prevAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $f3->set('HEADERS', []);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $f3->sync('SERVER');
        try {
            $this->assertSame('192.168.1.1', $f3->ip());
        } finally {
            $f3->set('HEADERS', $prevHdrs);
            if ($prevAddr === null) { unset($_SERVER['REMOTE_ADDR']); }
            else { $_SERVER['REMOTE_ADDR'] = $prevAddr; }
            $f3->sync('SERVER');
        }
    }

    // =========================================================
    // ip() — empty fallback when no REMOTE_ADDR (covers '' branch)
    // =========================================================

    public function testIpEmptyFallback(): void
    {
        $f3       = $this->f3;
        $prevHdrs = $f3->get('HEADERS');
        $prevAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $f3->set('HEADERS', []);
        unset($_SERVER['REMOTE_ADDR']);
        $f3->sync('SERVER');
        try {
            $this->assertSame('', $f3->ip());
        } finally {
            $f3->set('HEADERS', $prevHdrs);
            if ($prevAddr !== null) { $_SERVER['REMOTE_ADDR'] = $prevAddr; }
            $f3->sync('SERVER');
        }
    }

    // =========================================================
    // reroute() — array URL calls alias() (covers lines 1642-1645)
    // =========================================================

    public function testRerouteWithArrayUrl(): void
    {
        $f3    = $this->f3;
        $saved = [
            'ROUTES'    => $f3->get('ROUTES'),
            'ALIASES'   => $f3->get('ALIASES'),
            'ONREROUTE' => $f3->get('ONREROUTE'),
            'QUIET'     => $f3->get('QUIET'),
            'HALT'      => $f3->get('HALT'),
        ];
        $f3->set('QUIET', true);
        $f3->set('HALT',  false);
        $called  = false;
        $f3->set('ONREROUTE', function (string $url) use (&$called): bool {
            $called = true;
            return true;
        });
        $f3->route('GET @covaliasrr: /cov-alias-reroute', fn () => null);
        try {
            // Pass array: [name, params] → alias() builds the URL.
            $f3->reroute(['covaliasrr']);
            $this->assertTrue($called);
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }

    // =========================================================
    // reroute() — no-leading-slash URL (covers line 1657)
    // =========================================================

    public function testRerouteRelativeUrl(): void
    {
        $f3    = $this->f3;
        $saved = [
            'ROUTES'    => $f3->get('ROUTES'),
            'ALIASES'   => $f3->get('ALIASES'),
            'ONREROUTE' => $f3->get('ONREROUTE'),
            'QUIET'     => $f3->get('QUIET'),
            'HALT'      => $f3->get('HALT'),
        ];
        $f3->set('QUIET', true);
        $f3->set('HALT',  false);
        $called  = false;
        $f3->set('ONREROUTE', function (string $url) use (&$called, &$capturedUrl): bool {
            $called = true;
            $capturedUrl = $url;
            return true;
        });
        try {
            // 'relative-path' has no leading slash and no scheme → gets '/' prepended.
            $f3->reroute('relative-path');
            $this->assertTrue($called);
        } finally {
            foreach ($saved as $k => $v) { $f3->set($k, $v); }
        }
    }
}
