<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Base;
use PHPUnit\Framework\TestCase;
use Template;

/**
 * Template engine: variable interpolation, set, include, loop, check
 * (if/else), repeat, switch/case, exclude, ignore, loop, filter, counter,
 * nested includes.
 */
final class TemplateTest extends TestCase
{
    private Base $f3;
    private Template $tpl;
    private string $uiDir;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        $this->uiDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3ui-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->uiDir, 0777, true);
        $this->f3->set('UI', $this->uiDir);
        $this->f3->set('TEMP', $this->uiDir . 'tmp/');
        $this->tpl = Template::instance();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uiDir . '*') as $f) {
            if (is_dir($f)) {
                foreach (glob($f . DIRECTORY_SEPARATOR . '*') as $g) {
                    @unlink($g);
                }
                @rmdir($f);
            } else {
                @unlink($f);
            }
        }
        @rmdir($this->uiDir);
    }

    private function write(string $name, string $content): string
    {
        file_put_contents($this->uiDir . $name, $content);
        return $name;
    }

    // -- basics: interpolation, set, include, check, repeat -----------------

    public function testVariableInterpolation(): void
    {
        $this->write('var.htm', 'Hello, {{ @name }}!');
        $this->f3->set('name', 'Reactor');
        $this->assertSame('Hello, Reactor!', $this->tpl->render('var.htm'));
    }

    public function testSetTag(): void
    {
        $this->write('set.htm', '<set value="{{ 21*2 }}" />{{ @value }}');
        $out = $this->tpl->render('set.htm');
        $this->assertSame('42', $out);
    }

    public function testIncludeTag(): void
    {
        $this->write('part.htm', 'PART');
        $this->write('host.htm', 'A<include href="part.htm" />Z');
        $this->assertSame('APARTZ', $this->tpl->render('host.htm'));
    }

    public function testCheckIfTrue(): void
    {
        $this->write('check.htm',
            '<check if="{{ @flag }}"><true>Y</true><false>N</false></check>');
        $this->f3->set('flag', true);
        $this->assertSame('Y', $this->tpl->render('check.htm'));
        $this->f3->set('flag', false);
        $this->assertSame('N', $this->tpl->render('check.htm'));
    }

    public function testRepeatLoop(): void
    {
        $this->write('rep.htm',
            '<repeat group="{{ @items }}" value="{{ @item }}">[{{ @item }}]</repeat>');
        $this->f3->set('items', ['a', 'b', 'c']);
        $this->assertSame('[a][b][c]', $this->tpl->render('rep.htm'));
    }

    // -- advanced: switch/case, exclude, ignore, loop, filter, nested -------

    public function testExcludeStripsContent(): void
    {
        $this->write('e.htm', 'A<exclude>SECRET</exclude>Z');
        $this->assertSame('AZ', $this->tpl->render('e.htm'));
    }

    public function testIgnorePreservesRawContent(): void
    {
        $this->write('i.htm', '<ignore>{{ @x }}</ignore>');
        $this->f3->set('x', 'NEVER');
        $this->assertSame('{{ @x }}', $this->tpl->render('i.htm'));
    }

    public function testSwitchCaseDefault(): void
    {
        $tpl = '<switch expr="{{ @v }}">'
             . '<case value="a">A</case>'
             . '<case value="b">B</case>'
             . '<default>D</default>'
             . '</switch>';
        $this->write('s.htm', $tpl);

        $this->f3->set('v', 'a');
        $this->assertSame('A', $this->tpl->render('s.htm'));
        $this->f3->set('v', 'b');
        $this->assertSame('B', $this->tpl->render('s.htm'));
        $this->f3->set('v', 'zzz');
        $this->assertSame('D', $this->tpl->render('s.htm'));
    }

    public function testLoopTag(): void
    {
        $tpl = '<loop from="{{ @i=0 }}" to="{{ @i<3 }}" step="{{ @i++ }}">x</loop>';
        $this->write('l.htm', $tpl);
        $this->assertSame('xxx', $this->tpl->render('l.htm'));
    }

    public function testRepeatExposesKeyAndCounter(): void
    {
        $tpl = '<repeat group="{{ @items }}" key="{{ @k }}" value="{{ @v }}" '
             . 'counter="{{ @c }}">[{{ @c }}:{{ @k }}={{ @v }}]</repeat>';
        $this->write('r.htm', $tpl);
        $this->f3->set('items', ['x' => 1, 'y' => 2]);
        $out = $this->tpl->render('r.htm');
        $this->assertStringContainsString('[1:x=1]', $out);
        $this->assertStringContainsString('[2:y=2]', $out);
    }

    public function testNestedIncludes(): void
    {
        $this->write('inner.htm', 'IN');
        $this->write('mid.htm', '<include href="inner.htm" />');
        $this->write('outer.htm', '[<include href="mid.htm" />]');
        $this->assertSame('[IN]', $this->tpl->render('outer.htm'));
    }

    public function testCustomFilter(): void
    {
        // Filters fall back to global PHP functions when not registered.
        $this->write('f.htm', '{{ @msg | strtoupper }}');
        $this->f3->set('msg', 'hi');
        $this->assertSame('HI', $this->tpl->render('f.htm'));
    }

    // -- extend + __call + beforerender ------------------------------------

    public function testExtendRegistersCustomTagAndRenders(): void
    {
        // Register a <ping /> tag that always outputs 'pong'.
        $this->tpl->extend('ping', function (array $node): string {
            return '<?php echo \'pong\'; ?>';
        });
        $this->write('ping.htm', 'say:<ping />');
        $this->assertSame('say:pong', $this->tpl->render('ping.htm'));
    }

    public function testExtendCustomTagReceivesAttributes(): void
    {
        // Register a <shout label="..." /> tag that upper-cases the literal value.
        $this->tpl->extend('shout', function (array $node): string {
            $val = $node['@attrib']['label'] ?? '';
            return '<?php echo strtoupper(' . \Base::instance()->stringify($val) . '); ?>';
        });
        $this->write('shout.htm', '<shout label="hello" />');
        $this->assertSame('HELLO', $this->tpl->render('shout.htm'));
    }

    public function testBeforeRenderCallbackModifiesTemplateSource(): void
    {
        $this->write('bfr.htm', 'BEFORE');
        // Use a fresh Template instance to avoid polluting the shared singleton.
        $tpl = new \Template();
        $tpl->beforerender(function (string $src): string {
            return str_replace('BEFORE', 'AFTER', $src);
        });
        $out = $tpl->render('bfr.htm');
        $this->assertSame('AFTER', $out);
    }
}
