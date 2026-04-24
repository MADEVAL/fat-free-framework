<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;
use Preview;

/**
 * Preview engine: token expansion, filter chain, raw rendering of arbitrary
 * strings without UI files.
 */
final class PreviewTest extends TestCase
{
    private Base $f3;
    private Preview $p;

    protected function setUp(): void
    {
        $this->f3 = Base::instance();
        $this->p = Preview::instance();
    }

    public function testResolveSimpleToken(): void
    {
        $this->f3->set('name', 'World');
        $this->assertSame('Hello, World!', $this->p->resolve('Hello, {{ @name }}!'));
    }

    public function testResolveExpression(): void
    {
        $this->assertSame('5', $this->p->resolve('{{ 2+3 }}'));
    }

    public function testResolveFilterUsingPhpFunction(): void
    {
        // Preview falls back to global PHP functions when no filter is registered.
        $this->f3->set('x', 'abc');
        $this->assertSame('ABC', $this->p->resolve('{{ @x | strtoupper }}'));
    }

    public function testResolveBuiltInFormatFilter(): void
    {
        // format filter is wired to Base::format which expects 'integer' mode.
        $this->f3->set('n', 1234);
        $out = $this->p->resolve('{{ @n | format }}');
        $this->assertNotEmpty($out);
    }

    public function testResolveEscapesHtmlByDefault(): void
    {
        $prev = $this->f3->get('ESCAPE');
        $this->f3->set('ESCAPE', true);
        try {
            $this->f3->set('x', '<b>x</b>');
            $out = $this->p->resolve('{{ @x }}');
            $this->assertStringContainsString('&lt;b&gt;', $out);
        } finally {
            $this->f3->set('ESCAPE', $prev);
        }
    }

    public function testResolveRawWithRawFilter(): void
    {
        $prev = $this->f3->get('ESCAPE');
        $this->f3->set('ESCAPE', true);
        try {
            $this->f3->set('x', '<b>x</b>');
            $out = $this->p->resolve('{{ @x | raw }}');
            $this->assertStringContainsString('<b>x</b>', $out);
        } finally {
            $this->f3->set('ESCAPE', $prev);
        }
    }

    public function testFilterNoArgsReturnsFilterNames(): void
    {
        $keys = $this->p->filter();
        $this->assertIsArray($keys);
        foreach (['esc', 'raw', 'export', 'alias', 'format'] as $k) {
            $this->assertContains($k, $keys);
        }
    }

    public function testFilterGetByKeyReturnsRegisteredValue(): void
    {
        $val = $this->p->filter('esc');
        $this->assertSame('$this->esc', $val);
    }

    public function testFilterRegisterStringCallback(): void
    {
        $this->p->filter('pv_str_upper', 'strtoupper');
        $this->assertSame('strtoupper', $this->p->filter('pv_str_upper'));
    }

    public function testFilterRegisteredStringCallbackUsedInResolve(): void
    {
        $this->p->filter('pv_uc', 'strtoupper');
        $this->f3->set('pv_w', 'hello');
        $out = $this->p->resolve('{{ @pv_w | pv_uc }}', null, 0, false, false);
        $this->assertSame('HELLO', $out);
    }

    public function testFilterRegisteredClosureUsedInResolve(): void
    {
        $this->p->filter('pv_star', fn ($v) => $v . '*');
        $this->f3->set('pv_s', 'ok');
        $out = $this->p->resolve('{{ @pv_s | pv_star }}', null, 0, false, false);
        $this->assertSame('ok*', $out);
    }

    public function testTokenConvertsVariableTokenToPhpVar(): void
    {
        $result = $this->p->token('{{ @foo }}');
        $this->assertSame('$foo', $result);
    }

    public function testTokenConvertsNestedPropertyToPhpArrayAccess(): void
    {
        $result = $this->p->token('{{ @user.name }}');
        $this->assertStringContainsString('$user', $result);
        $this->assertStringContainsString('name', $result);
    }

    public function testInterpolationFalsePreventsTailingNewlineEmbedding(): void
    {
        $this->f3->set('pv_v', 'val');
        $this->p->interpolation(false);
        try {
            // With interpolation=off the trailing newline is in the PHP source after
            // the close tag, where PHP's parser consumes it (single newline rule).
            $out = $this->p->resolve("{{ @pv_v }}\n", null, 0, false, false);
            $this->assertSame('val', $out);
        } finally {
            $this->p->interpolation(true);
        }
    }
}
