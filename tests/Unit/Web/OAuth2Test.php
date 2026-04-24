<?php

declare(strict_types=1);

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Tests\Support\MockWeb;
use Web\OAuth2;

final class OAuth2Test extends TestCase
{
    protected function tearDown(): void
    {
        MockWeb::restore();
    }

    public function testUriBuildsAuthorizationEndpoint(): void
    {
        $oa = new OAuth2();
        $oa->set('client_id', 'abc');
        $oa->set('redirect_uri', 'https://app/cb');
        $uri = $oa->uri('https://issuer/auth');
        $this->assertStringContainsString('client_id=abc', $uri);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp%2Fcb', $uri);
    }

    public function testUriWithoutQuery(): void
    {
        $oa = new OAuth2();
        $oa->set('client_id', 'abc');
        $this->assertSame('https://issuer/auth', $oa->uri('https://issuer/auth', false));
    }

    public function testRequestReturnsDecodedJson(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue(json_encode(['access_token' => 'tok', 'expires_in' => 3600]),
            ['HTTP/1.1 200 OK', 'Content-Type: application/json; charset=utf-8']);

        $oa = new OAuth2();
        $oa->set('client_id', 'cid');
        $oa->set('client_secret', 'sec');
        $token = $oa->request('https://issuer/token', 'POST');

        $this->assertSame('tok', $token['access_token']);
        $auth = null;
        foreach ($mock->calls[0]['options']['header'] as $h) {
            if (stripos($h, 'Authorization: Basic ') === 0) {
                $auth = $h;
            }
        }
        $this->assertNotNull($auth);
    }

    public function testRequestWithBearerToken(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue('plain-body', ['HTTP/1.1 200 OK', 'Content-Type: text/plain']);
        $oa = new OAuth2();
        $body = $oa->request('https://api/me', 'GET', 'mytoken');
        $this->assertSame('plain-body', $body);
        $this->assertContains('Authorization: Bearer mytoken', $mock->calls[0]['options']['header']);
    }

    public function testRequestThrowsOnErrorEnvelope(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue(json_encode(['error' => 'invalid_grant']),
            ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json']);
        $this->expectException(\Exception::class);
        $oa = new OAuth2();
        $oa->set('client_id', 'a');
        $oa->set('client_secret', 'b');
        $oa->request('https://issuer/token', 'POST');
    }

    public function testJwtParsesPayload(): void
    {
        $oa = new OAuth2();
        $payload = ['sub' => 'u1', 'iat' => 1000];
        $jwt = 'h.' . rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=') . '.s';
        $this->assertSame('u1', $oa->jwt($jwt)['sub']);
    }

    public function testB64UrlEncoding(): void
    {
        $oa = new OAuth2();
        $this->assertSame('YWJjPw', $oa->b64url('abc?'));
    }

    public function testMagicAccess(): void
    {
        $oa = new OAuth2();
        $oa->set('scope', 'openid');
        $this->assertTrue($oa->exists('scope'));
        $this->assertSame('openid', $oa->get('scope'));
        $oa->clear('scope');
        $this->assertFalse($oa->exists('scope'));
    }

    public function testSetEncoding(): void
    {
        $oa = new OAuth2();
        $oa->setEncoding(PHP_QUERY_RFC3986);
        $oa->set('redirect_uri', 'https://x/cb');
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fx%2Fcb', $oa->uri('https://e'));
    }
}
