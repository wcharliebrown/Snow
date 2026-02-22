<?php
/**
 * Test Bootstrap for Snow Framework
 * Sets up constants, loads config, and initialises DB for testing
 */

define('SNOW_ROOT',      dirname(__DIR__));
define('SNOW_PUBLIC',    SNOW_ROOT . '/public_html');
define('SNOW_TEMPLATES', SNOW_ROOT . '/templates');
define('SNOW_FUNCTIONS', SNOW_ROOT . '/functions');
define('SNOW_LOGS',      SNOW_ROOT . '/logs');
define('SNOW_KEYS',      SNOW_ROOT . '/keys');
define('SNOW_PLUGINS',   SNOW_ROOT . '/plugins');
define('SNOW_SNAPSHOTS', SNOW_ROOT . '/snapshots');
define('SNOW_VERSION',   '1.0.0');

// Load .env config
$envFile = SNOW_ROOT . '/.env';
if (!file_exists($envFile)) {
    die("ERROR: .env file not found at $envFile\n");
}
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value);
    // Strip surrounding quotes
    if (preg_match('/^(["\'])(.+)\1$/', $value, $m)) $value = $m[2];
    // Respect values already set in the environment (e.g. Docker Compose overrides)
    if (getenv($key) === false) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Fake session superglobal for CLI
if (!isset($_SESSION)) {
    $_SESSION = [];
}

// Fake server superglobal for logging functions
if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'SnowTestRunner/1.0';
    $_SERVER['REQUEST_URI']    = '/test';
    $_SERVER['SCRIPT_NAME']    = '/index.php';
}

// Suppress header/session warnings that are CLI-only artefacts
// (setcookie and header() cannot work in CLI; not production bugs)
set_error_handler(function (int $errno, string $errstr): bool {
    if (str_contains($errstr, 'headers already sent') ||
        str_contains($errstr, 'Cannot modify header') ||
        str_contains($errstr, 'Session cannot be started after headers') ||
        str_contains($errstr, 'Trying to destroy uninitialized session')) {
        return true; // suppress
    }
    return false; // delegate to default handler
});

// Start a real CLI session so session functions work
session_start();

// Load all framework functions in the correct order
require_once SNOW_FUNCTIONS . '/logging.php';
require_once SNOW_FUNCTIONS . '/database.php';
require_once SNOW_FUNCTIONS . '/auth.php';
require_once SNOW_FUNCTIONS . '/template.php';
require_once SNOW_FUNCTIONS . '/encryption.php';
require_once SNOW_FUNCTIONS . '/pages.php';
require_once SNOW_FUNCTIONS . '/reports.php';
require_once SNOW_FUNCTIONS . '/email.php';
