<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Auth;
use Bcrypt;
use DB\Jig;
use DB\Jig\Mapper as JigMapper;
use DB\SQL;
use DB\SQL\Mapper as SqlMapper;
use PHPUnit\Framework\TestCase;

/**
 * Auth login flow against Jig and SQL backends with a Bcrypt verifier.
 */
final class AuthTest extends TestCase
{
    private string $jigDir;
    private Jig $jig;
    private SQL $sql;

    protected function setUp(): void
    {
        $this->jigDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f3auth-' . uniqid() . DIRECTORY_SEPARATOR;
        $this->jig = new Jig($this->jigDir, Jig::FORMAT_JSON);

        $bc = Bcrypt::instance();
        $hash = $bc->hash('s3cret', null, 4);

        $u = new JigMapper($this->jig, 'users');
        $u->user = 'alice';
        $u->pw   = $hash;
        $u->save();

        $this->sql = new SQL('sqlite::memory:');
        $this->sql->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, user TEXT, pw TEXT)'
        );
        $this->sql->exec('INSERT INTO users (user,pw) VALUES (?,?)', ['bob', $hash]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->jigDir)) {
            foreach (glob($this->jigDir . '*') as $f) {
                @unlink($f);
            }
            @rmdir($this->jigDir);
        }
    }

    public function testJigLoginAcceptsValidCredential(): void
    {
        $mapper = new JigMapper($this->jig, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw'],
            fn ($plain, $hash) => Bcrypt::instance()->verify($plain, $hash));
        $this->assertTrue($auth->login('alice', 's3cret'));
    }

    public function testJigLoginRejectsBadPassword(): void
    {
        $mapper = new JigMapper($this->jig, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw'],
            fn ($plain, $hash) => Bcrypt::instance()->verify($plain, $hash));
        $this->assertFalse($auth->login('alice', 'wrong'));
    }

    public function testJigLoginRejectsUnknownUser(): void
    {
        $mapper = new JigMapper($this->jig, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw']);
        $this->assertFalse($auth->login('ghost', 'x'));
    }

    public function testSqlLoginAcceptsValidCredential(): void
    {
        $mapper = new SqlMapper($this->sql, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw'],
            fn ($plain, $hash) => Bcrypt::instance()->verify($plain, $hash));
        $this->assertTrue($auth->login('bob', 's3cret'));
    }

    public function testSqlLoginRejectsBadPassword(): void
    {
        $mapper = new SqlMapper($this->sql, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw'],
            fn ($plain, $hash) => Bcrypt::instance()->verify($plain, $hash));
        $this->assertFalse($auth->login('bob', 'wrong'));
    }

    public function testBasicWithPhpAuthCredentials(): void
    {
        $mapper = new JigMapper($this->jig, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw'],
            fn ($plain, $hash) => Bcrypt::instance()->verify($plain, $hash));

        $_SERVER['PHP_AUTH_USER'] = 'alice';
        $_SERVER['PHP_AUTH_PW']   = 's3cret';
        try {
            $this->assertTrue($auth->basic());
        } finally {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        }
    }

    public function testBasicDecodesHttpAuthorizationHeader(): void
    {
        $mapper = new JigMapper($this->jig, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw'],
            fn ($plain, $hash) => Bcrypt::instance()->verify($plain, $hash));

        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('alice:s3cret');
        try {
            $this->assertTrue($auth->basic());
        } finally {
            unset($_SERVER['HTTP_AUTHORIZATION'],
                  $_SERVER['PHP_AUTH_USER'],
                  $_SERVER['PHP_AUTH_PW']);
        }
    }

    public function testBasicWithNoCredentialsReturnsFalse(): void
    {
        $mapper = new JigMapper($this->jig, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw']);

        unset($_SERVER['PHP_AUTH_USER'],
              $_SERVER['PHP_AUTH_PW'],
              $_SERVER['HTTP_AUTHORIZATION'],
              $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);

        $this->assertFalse($auth->basic());
    }

    public function testSqlLoginWithoutFuncUsesPlainMatch(): void
    {
        // Insert a user with a plain-text password.
        $this->sql->exec('INSERT INTO users (user,pw) VALUES (?,?)', ['carol', 'plain123']);
        $mapper = new SqlMapper($this->sql, 'users');
        $auth = new Auth($mapper, ['id' => 'user', 'pw' => 'pw']);
        $this->assertTrue($auth->login('carol', 'plain123'));
        $this->assertFalse($auth->login('carol', 'wrong'));
    }

    public function testJigLoginWithRealmFilter(): void
    {
        $bc   = Bcrypt::instance();
        $hash = $bc->hash('pass', null, 4);
        $u    = new JigMapper($this->jig, 'users');
        $u->user  = 'dave';
        $u->pw    = $hash;
        $u->realm = 'admin';
        $u->save();

        $mapper = new JigMapper($this->jig, 'users');
        $auth   = new Auth($mapper,
            ['id' => 'user', 'pw' => 'pw', 'realm' => 'realm'],
            fn ($plain, $stored) => Bcrypt::instance()->verify($plain, $stored));

        $this->assertTrue($auth->login('dave', 'pass', 'admin'));
        $this->assertFalse($auth->login('dave', 'pass', 'other'));
    }
}
