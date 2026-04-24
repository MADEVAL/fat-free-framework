<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Base;
use PHPUnit\Framework\TestCase;
use View;

/**
 * View class: esc/raw, file-based render, afterrender hook.
 * Each test manages its own temp UI directory to avoid interference.
 */
final class ViewTest extends TestCase
{
    private Base $f3;
    private string $uiDir;
    private string $prevUi;
    private View $view;

    protected function setUp(): void
    {
        $this->f3     = Base::instance();
        $this->uiDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3view-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->uiDir, 0755, true);
        $this->prevUi = $this->f3->get('UI');
        $this->f3->set('UI', $this->uiDir);
        $this->view = View::instance();
    }

    protected function tearDown(): void
    {
        $this->f3->set('UI', $this->prevUi);
        foreach (glob($this->uiDir . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->uiDir);
    }

    // -- esc / raw ----------------------------------------------------------

    public function testEscConvertsSpecialChars(): void
    {
        $out = $this->view->esc('<b>hello & "world"</b>');
        $this->assertStringContainsString('&lt;b&gt;', $out);
        $this->assertStringContainsString('&amp;', $out);
        $this->assertStringContainsString('&quot;', $out);
    }

    public function testEscRecursivelyHandlesArrayValues(): void
    {
        $out = $this->view->esc(['a' => '<x>', 'b' => 'clean']);
        $this->assertSame('&lt;x&gt;', $out['a']);
        $this->assertSame('clean', $out['b']);
    }

    public function testEscPassesNonStringThroughUnchanged(): void
    {
        $out = $this->view->esc(42);
        $this->assertSame(42, $out);
    }

    public function testRawDecodesHtmlEntities(): void
    {
        $out = $this->view->raw('&lt;b&gt;hello&lt;/b&gt;');
        $this->assertSame('<b>hello</b>', $out);
    }

    public function testRawRecursivelyHandlesArray(): void
    {
        $out = $this->view->raw(['a' => '&amp;', 'b' => '&lt;']);
        $this->assertSame('&', $out['a']);
        $this->assertSame('<', $out['b']);
    }

    // -- render -------------------------------------------------------------

    public function testRenderOutputsTemplateResult(): void
    {
        file_put_contents($this->uiDir . 'hello.php', '<?php echo "rendered:" . $greeting; ?>');
        $out = $this->view->render('hello.php', 'text/plain', ['greeting' => 'world']);
        $this->assertSame('rendered:world', $out);
    }

    public function testRenderEscapesHiveWhenEscapeIsOn(): void
    {
        $prev = $this->f3->get('ESCAPE');
        $this->f3->set('ESCAPE', true);
        try {
            file_put_contents($this->uiDir . 'esc.php', '<?php echo $unsafe; ?>');
            $out = $this->view->render('esc.php', 'text/html', ['unsafe' => '<b>x</b>']);
            $this->assertStringContainsString('&lt;b&gt;', $out);
        } finally {
            $this->f3->set('ESCAPE', $prev);
        }
    }

    public function testRenderThrowsForMissingFile(): void
    {
        $this->expectException(\Exception::class);
        $this->view->render('no-such-file-' . uniqid() . '.php');
    }

    public function testRenderWithExplicitHive(): void
    {
        file_put_contents($this->uiDir . 'item.php', '<?php echo $item; ?>');
        $out = $this->view->render('item.php', 'text/plain', ['item' => 'apple']);
        $this->assertSame('apple', $out);
    }

    // -- afterrender --------------------------------------------------------

    public function testAfterRenderCallbackModifiesOutput(): void
    {
        file_put_contents($this->uiDir . 'hook.php', '<?php echo "original"; ?>');

        // Use a fresh View instance to avoid hooks from previous tests.
        $v = new View();
        $v->afterrender(fn ($html) => strtoupper($html));
        $out = $v->render('hook.php', 'text/plain');
        $this->assertSame('ORIGINAL', $out);
    }

    public function testAfterRenderCallbackReceivesFilePath(): void
    {
        file_put_contents($this->uiDir . 'path.php', '<?php echo "x"; ?>');

        $receivedPath = null;
        $v = new View();
        $v->afterrender(function ($html, $file) use (&$receivedPath) {
            $receivedPath = $file;
            return $html;
        });
        $v->render('path.php', 'text/plain');
        $this->assertStringContainsString('path.php', $receivedPath);
    }

    public function testMultipleAfterRenderCallbacksChain(): void
    {
        file_put_contents($this->uiDir . 'chain.php', '<?php echo "ab"; ?>');

        $v = new View();
        $v->afterrender(fn ($html) => $html . 'c');
        $v->afterrender(fn ($html) => $html . 'd');
        $out = $v->render('chain.php', 'text/plain');
        $this->assertSame('abcd', $out);
    }
}
