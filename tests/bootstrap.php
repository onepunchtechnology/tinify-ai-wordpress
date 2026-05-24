<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
// Brain\Monkey::setUp() and tearDown() are called per-test in TestCase subclasses.

// WordPress constants used by plugin code
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
// DIRECTORY_SEPARATOR is a PHP built-in constant — don't redefine it
define('AUTH_KEY', 'test-auth-key');
define('SECURE_AUTH_KEY', 'test-secure-auth-key');
