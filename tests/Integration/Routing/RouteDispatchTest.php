<?php

declare(strict_types=1);

namespace Tests\Integration\Routing;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Full routing dispatch via Base::mock - covers GROUP, REST verb maps,
 * before/after hooks and HEAD-as-GET fallback.
 */
final class RouteDispatchTest extends TestCase
{
    private Base $f3;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        $this->f3->clear('ROUTES');
        $this->f3->clear('ALIASES');
        $this->f3->set('QUIET', true);
        $this->f3->set('HALT', false);
        $this->f3->set('CACHE', false);
        $this->f3->set('LOGGABLE', '');
        $this->f3->set('ONERROR', fn ($f3) => true);
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $this->f3->sync('SERVER');
    }

    protected function tearDown(): void
    {
        $this->f3->clear('ROUTES');
        $this->f3->clear('RESPONSE');
        $this->f3->clear('ONERROR');
        $this->f3->set('LOGGABLE', '*');
    }

    public function testBasicGetDispatch(): void
    {
        $this->f3->route('GET /hello', function ($f3) {
            echo 'world';
        });
        $this->f3->mock('GET /hello');
        $this->assertSame('world', $this->f3->get('RESPONSE'));
    }

    public function testHeadRequestRoutesToHandler(): void
    {
        $called = (object) ['hit' => false];
        $this->f3->route('GET|HEAD /resource', function () use ($called) {
            $called->hit = true;
            echo 'body';
        });
        $this->f3->mock('HEAD /resource');
        $this->assertTrue($called->hit);
    }

    public function testRestVerbMapDispatchesCorrectMethod(): void
    {
        $this->f3->map('/users/@id', new class {
            public function get($f3, $args) { echo 'GET-' . $args['id']; }
            public function post($f3, $args) { echo 'POST-' . $args['id']; }
            public function put($f3, $args) { echo 'PUT-' . $args['id']; }
            public function delete($f3, $args) { echo 'DEL-' . $args['id']; }
        });

        $this->f3->mock('GET /users/42');
        $this->assertSame('GET-42', $this->f3->get('RESPONSE'));

        $this->f3->mock('POST /users/9');
        $this->assertSame('POST-9', $this->f3->get('RESPONSE'));

        $this->f3->mock('DELETE /users/1');
        $this->assertSame('DEL-1', $this->f3->get('RESPONSE'));
    }

    public function testBeforeAndAfterRouteHooksOnHandler(): void
    {
        $this->f3->set('order', '');
        $this->f3->map('/trace', new class {
            public function beforeroute($f3) { $f3->set('order', $f3->get('order') . 'B'); }
            public function get($f3) { $f3->set('order', $f3->get('order') . 'M'); echo 'done'; }
            public function afterroute($f3) { $f3->set('order', $f3->get('order') . 'A'); }
        });
        $this->f3->mock('GET /trace');
        $this->assertSame('BMA', $this->f3->get('order'));
    }

    public function testNamedRouteAliasBuildsUrl(): void
    {
        $this->f3->route('GET @profile: /user/@name', function () {});
        $url = $this->f3->alias('profile', ['name' => 'jane']);
        $this->assertSame('/user/jane', $url);
    }

    public function testWildcardRouteCapturesPath(): void
    {
        $this->f3->route('GET /files/*', function ($f3) {
            echo $f3->get('PARAMS')['*'];
        });
        $this->f3->mock('GET /files/some/path');
        $this->assertSame('some/path', $this->f3->get('PARAMS')['*']);
    }

    public function testCliModeDispatchesHandler(): void
    {
        $this->f3->route('GET /cli-test', function ($f3) {
            echo 'cli-ok';
        });
        $this->f3->mock('GET /cli-test [cli]');
        $this->assertSame('cli-ok', $this->f3->get('RESPONSE'));
    }

    public function testUnknownRouteProduces404(): void
    {
        $this->f3->route('GET /exists', function () {});
        $this->f3->clear('ERROR');
        $this->f3->mock('GET /does-not-exist');
        $error = $this->f3->get('ERROR');
        $this->assertIsArray($error);
        $this->assertSame(404, $error['code']);
    }

    public function testRedirectRegistersRouteForPattern(): void
    {
        $this->f3->redirect('GET /old', '/new');
        $routes = $this->f3->get('ROUTES');
        $this->assertArrayHasKey('/old', $routes);
    }
}
