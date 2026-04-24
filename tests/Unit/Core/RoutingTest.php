<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Routing primitives: route registration, named aliases, URL building and
 * mocked request dispatch. We never fire a real HTTP request: Base::mock()
 * simulates one and Base::run() invokes the matching handler.
 */
final class RoutingTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        // Wipe any previously-registered routes between tests.
        $this->f3->set('ROUTES', []);
        $this->f3->set('ALIASES', []);
        $this->f3->set('QUIET', true);
        $this->f3->set('HALT', false);
        $this->f3->set('ERROR', null);
        $this->f3->set('LOGGABLE', '');
        // Required by Base::status() to read SERVER_PROTOCOL when sending headers.
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $this->f3->sync('SERVER');
    }

    protected function tearDown(): void
    {
        $this->f3->set('ROUTES', []);
        $this->f3->set('ALIASES', []);
        $this->f3->set('QUIET', false);
        $this->f3->set('HALT', true);
        $this->f3->set('ERROR', null);
        $this->f3->set('LOGGABLE', '*');
    }

    public function testRouteRegistersInRoutesHive(): void
    {
        $this->f3->route('GET /hello', fn() => 'hi');
        $routes = $this->f3->get('ROUTES');
        $this->assertNotEmpty($routes);
        $this->assertArrayHasKey('/hello', $routes);
    }

    public function testNamedRouteCreatesAlias(): void
    {
        $this->f3->route('GET @greet: /hello/@name', fn(Base $f3, array $args) => $args['name']);
        $aliases = $this->f3->get('ALIASES');
        $this->assertArrayHasKey('greet', $aliases);
        $this->assertSame('/hello/@name', $aliases['greet']);
    }

    public function testAliasBuildsUrlWithToken(): void
    {
        $this->f3->route('GET @user: /user/@id', fn() => null);
        $url = $this->f3->alias('user', ['id' => 42]);
        $this->assertSame('/user/42', $url);
    }

    public function testBuildSubstitutesTokens(): void
    {
        $this->assertSame('/u/77', $this->f3->build('/u/@id', ['id' => 77]));
    }

    public function testMockDispatchesHandlerAndCapturesReturnedValue(): void
    {
        $this->f3->route('GET /sum/@a/@b',
            function (Base $f3, array $args) {
                echo (int) $args['a'] + (int) $args['b'];
            }
        );
        $this->f3->mock('GET /sum/2/3');
        $this->assertSame('5', $this->f3->get('RESPONSE'));
    }

    public function testMockSetsParams(): void
    {
        $this->f3->route('GET /item/@id',
            function (Base $f3, array $args) {
                $f3->set('captured', $args);
            }
        );
        $this->f3->mock('GET /item/abc');
        $this->assertSame('abc', $this->f3->get('captured.id'));
    }

    public function testUnknownRouteReturns404Status(): void
    {
        // Need at least one route registered or F3 raises 'No routes specified'.
        $this->f3->route('GET /home', fn() => null);
        $this->f3->set('ONERROR', function (Base $f3) {
            $f3->set('errCode', $f3->get('ERROR.code'));
            return true;
        });
        $this->f3->mock('GET /nowhere');
        $this->assertSame(404, $this->f3->get('errCode'));
    }

    public function testMethodNotAllowedReturns405(): void
    {
        $this->f3->route('GET /only', fn() => 'x');
        $this->f3->set('ONERROR', function (Base $f3) {
            $f3->set('errCode', $f3->get('ERROR.code'));
            return true;
        });
        $this->f3->mock('POST /only');
        $this->assertSame(405, $this->f3->get('errCode'));
    }

    public function testWildcardRouteCapturesRest(): void
    {
        $this->f3->route('GET /assets/*',
            function (Base $f3, array $args) {
                echo $args[0] ?? '';
            }
        );
        $this->f3->mock('GET /assets/img/logo.png');
        // F3 wildcard $args[0] preserves the leading slash of the matched portion.
        $this->assertStringContainsString('img/logo.png', $this->f3->get('RESPONSE'));
    }

    public function testRedirectRegistersRouteInHive(): void
    {
        $this->f3->redirect('GET /legacy', '/new-location');
        $routes = $this->f3->get('ROUTES');
        $this->assertArrayHasKey('/legacy', $routes);
    }

    public function testRerouteInCliModeDispatchesInternally(): void
    {
        $this->f3->set('CLI', true);
        $this->f3->route('GET /cli-target [cli]', function () {
            echo 'cli-reached';
        });
        $this->f3->reroute('/cli-target');
        $this->assertSame('cli-reached', $this->f3->get('RESPONSE'));
        $this->f3->set('CLI', false);
    }

    public function testMapRegistersRouteForUrl(): void
    {
        $this->f3->map('/api/items', 'ResourceHandler');
        $routes = $this->f3->get('ROUTES');
        $this->assertArrayHasKey('/api/items', $routes);
    }

    public function testMapWithArrayOfUrlsRegistersEachPattern(): void
    {
        $this->f3->map(['/x', '/y'], 'MultiHandler');
        $routes = $this->f3->get('ROUTES');
        $this->assertArrayHasKey('/x', $routes);
        $this->assertArrayHasKey('/y', $routes);
    }
}
