<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

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
}
