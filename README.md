# fatfree-core (Modern)

Fork of [Fat-Free Framework](https://fatfreeframework.com/) core, kept compatible with PHP 8.x including 8.5.

For the upstream vanilla version use [bcosca/fatfree-core](https://github.com/bcosca/fatfree-core).

## Differences from upstream

### PHP 8.5 compatibility

| Change | Reason |
|---|---|
| `Preview::c()`: dropped `setlocale(LC_NUMERIC, 0)` | PHP 8 made `int` second arg a `TypeError`. PHP 8 path is locale-independent; PHP 7 fallback uses string `'0'`. |
| `Web::_curl()`: version check for `CURLOPT_PROTOCOLS` | libcurl 7.85+ deprecated `CURLPROTO_*` masks. Uses `CURLOPT_PROTOCOLS_STR` when available, falls back otherwise. |
| Removed `web/pingback.php` | Depended on `xmlrpc_*` functions removed in PHP 8.0+. The `xmlrpc` extension is no longer maintained. |
| Removed cache backends `xcache`, `wincache`, `memcache` | All dead on modern PHP. Remaining: `apc`/`apcu`, `memcached`, `redis`, `folder`. |

### Tests

* [tests/](tests/) with PHPUnit 11, split into `unit` and `integration`.
* DSNs configured via `<env>` in [phpunit.xml](phpunit.xml).
* MySQL, PostgreSQL, MongoDB, SQLite covered. Integration tests skip cleanly if a service is unreachable.
* Tests excluded from production autoload via `exclude-from-classmap`, loaded via PSR-4 `Tests\\`.

### Dependencies

* Minimum PHP raised from 7.2 to 7.4.
* `mongodb/mongodb ^2.2` added to `require-dev` for `\DB\Mongo` (uses `MongoDB\Client`).

### Backward compatibility

Public API unchanged. Application code keeps working.

## Install

```bash
composer require globus-studio/fatfree-core
```

```php
require 'vendor/autoload.php';
$f3 = \Base::instance();
```

Without Composer:

```php
$f3 = require 'lib/base.php';
```

URL rewrite required, see [routing-engine](https://fatfreeframework.com/3.6/routing-engine#DynamicWebSites).

## Run tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration
```

DB connection vars: `DB_MYSQL_DSN`, `DB_MYSQL_USER`, `DB_MYSQL_PASS`, `DB_MYSQL_NAME`, same prefix for `DB_PGSQL_*`, plus `DB_MONGO_URI` and `DB_MONGO_NAME`.

## Built on this core

**[Atomic Framework](https://github.com/MADEVAL/Atomic-Framework)** is a full-featured PHP framework built on top of this core. It adds a structured application layer with: authentication (bcrypt, OAuth 2.0, Telegram, rate limiting, impersonation), MySQL/Redis/Memcached via `ConnectionManager`, timestamp-based migrations, Redis/DB queue with retry and monitoring, POSIX cron scheduler, multi-driver cache with cascade fallback, parameterized middleware, hierarchical event dispatcher, WordPress-compatible hook/filter layer, SMTP mailer with DNS deliverability scoring, i18n with URL prefixing, 45+ CLI commands, NaCl/libsodium encryption, WebSocket server (Workerman + Redis pub/sub), and a plugin lifecycle system. Requires PHP >= 8.1.

**[Atomic-Framework-Application](https://github.com/MADEVAL/Atomic-Framework-Application)** is the official application skeleton/template for Atomic Framework.

## Links

* Upstream demo: https://github.com/bcosca/fatfree
* User Guide: https://fatfreeframework.com/user-guide
* API Reference: https://fatfreeframework.com/api-reference
* License: GPL-3.0, see [COPYING](COPYING)
