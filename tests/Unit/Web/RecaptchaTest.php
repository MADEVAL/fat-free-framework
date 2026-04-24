<?php

declare(strict_types=1);

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Tests\Support\MockWeb;
use Web\Google\Recaptcha;

final class RecaptchaTest extends TestCase
{
    protected function tearDown(): void
    {
        MockWeb::restore();
    }

    public function testVerifyReturnsTrueOnSuccess(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue(json_encode(['success' => true]));
        $this->assertTrue(Recaptcha::verify('secret', 'token'));
        $this->assertSame('https://www.google.com/recaptcha/api/siteverify', $mock->calls[0]['url']);
    }

    public function testVerifyReturnsFalseOnFailure(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue(json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]));
        $this->assertFalse(Recaptcha::verify('secret', 'token'));
    }

    public function testVerifyReturnsFalseWithoutResponse(): void
    {
        MockWeb::install();
        \Base::instance()->clear('POST.g-recaptcha-response');
        $this->assertFalse(Recaptcha::verify('secret'));
    }
}
