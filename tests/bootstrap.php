<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for Fat-Free Framework tests.
 * Loads Composer autoloader (which classmap-autoloads F3 sources)
 * and initialises the global F3 instance with safe defaults.
 */

require __DIR__ . '/../vendor/autoload.php';

// Initialise the global Base instance once, with a writable temp dir
$f3 = \Base::instance();
$f3->set('TEMP', __DIR__ . '/_tmp/');
$f3->set('CACHE', false);
$f3->set('DEBUG', 0);

if (!is_dir(__DIR__ . '/_tmp')) {
    mkdir(__DIR__ . '/_tmp', 0777, true);
}
