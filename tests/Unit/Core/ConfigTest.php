<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;

/**
 * Base::config - INI-style config file loading with sections.
 */
final class ConfigTest extends TestCase
{
    private Base $f3;
    private string $cfg;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        $this->cfg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3cfg-' . uniqid() . '.ini';
        file_put_contents($this->cfg, <<<INI
[globals]
APP.name = "Reactor"
APP.cores = 4
APP.online = true

[routes]
GET /home = "App->home"
INI);
    }

    protected function tearDown(): void
    {
        @unlink($this->cfg);
        $this->f3->clear('APP');
        $this->f3->clear('ROUTES');
    }

    public function testGlobalsSectionPopulatesHive(): void
    {
        $this->f3->config($this->cfg);
        $this->assertSame('Reactor', $this->f3->get('APP.name'));
        $this->assertSame(4, $this->f3->get('APP.cores'));
        $this->assertTrue($this->f3->get('APP.online'));
    }

    public function testRoutesSectionRegistersRoute(): void
    {
        $this->f3->config($this->cfg);
        $routes = $this->f3->get('ROUTES');
        $this->assertNotEmpty($routes);
    }

    public function testRedirectsSectionRegistersRedirectRoute(): void
    {
        $cfg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3redir-' . uniqid() . '.ini';
        file_put_contents($cfg, "[redirects]\nGET /old = /new\n");
        $this->f3->config($cfg);
        @unlink($cfg);
        $routes = $this->f3->get('ROUTES');
        $this->assertArrayHasKey('/old', $routes);
    }

    public function testCustomSectionSetsNamespacedHiveKeys(): void
    {
        $cfg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3custom-' . uniqid() . '.ini';
        file_put_contents($cfg, "[MYAPP]\nfoo = bar\nbaz = 42\n");
        $this->f3->config($cfg);
        @unlink($cfg);
        $this->assertSame('bar', $this->f3->get('MYAPP.foo'));
        $this->assertSame(42,    $this->f3->get('MYAPP.baz'));
        $this->f3->clear('MYAPP');
    }

    public function testMapsSectionRegistersAllHttpVerbs(): void
    {
        $cfg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3maps-' . uniqid() . '.ini';
        file_put_contents($cfg, "[maps]\n/widgets = WidgetHandler\n");
        $this->f3->config($cfg);
        @unlink($cfg);
        $routes = $this->f3->get('ROUTES');
        $this->assertArrayHasKey('/widgets', $routes);
    }
}
