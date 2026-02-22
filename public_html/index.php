<?php
/**
 * Snow Framework - Main Bootstrap File
 * This is the entry point for all requests
 */

// Error reporting based on environment
if (getenv('DEV_MODE') == '1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Define framework constants
define('SNOW_VERSION', '1.0.0');
define('SNOW_ROOT', dirname(__DIR__));
define('SNOW_PUBLIC', __DIR__);
define('SNOW_TEMPLATES', SNOW_ROOT . '/templates');
define('SNOW_FUNCTIONS', SNOW_ROOT . '/functions');
define('SNOW_LOGS', SNOW_ROOT . '/logs');
define('SNOW_KEYS', SNOW_ROOT . '/keys');
define('SNOW_PLUGINS', SNOW_ROOT . '/plugins');
define('SNOW_SNAPSHOTS', SNOW_ROOT . '/snapshots');

// Load configuration
function loadConfig() {
    $envFile = SNOW_ROOT . '/.env';
    if (!file_exists($envFile)) {
        die('Configuration file not found. Please create .env file.');
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes if present
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Respect values already set in the environment (e.g. Docker Compose overrides)
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Autoload function files
function autoloadFunctions($className) {
    $functionFile = SNOW_FUNCTIONS . '/' . strtolower($className) . '.php';
    if (file_exists($functionFile)) {
        require_once $functionFile;
    }
}

// Initialize framework
function initializeFramework() {
    // Load configuration
    loadConfig();
    
    // Set up autoloader
    spl_autoload_register('autoloadFunctions');
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', getenv('HTTPS') ? 1 : 0);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
    
    // Set default timezone
    date_default_timezone_set('UTC');
    
// Load core functions in correct order
require_once SNOW_FUNCTIONS . '/logging.php';
require_once SNOW_FUNCTIONS . '/database.php';
require_once SNOW_FUNCTIONS . '/auth.php';
require_once SNOW_FUNCTIONS . '/template.php';
require_once SNOW_FUNCTIONS . '/encryption.php';
require_once SNOW_FUNCTIONS . '/pages.php';
require_once SNOW_FUNCTIONS . '/reports.php';
require_once SNOW_FUNCTIONS . '/email.php';
}

// Route request
function routeRequest() {
    $requestUri = $_SERVER['REQUEST_URI'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Remove query string
    $requestUri = strtok($requestUri, '?');
    
    // Remove script name if present
    if (strpos($requestUri, $scriptName) === 0) {
        $requestUri = substr($requestUri, strlen($scriptName));
    }
    
    // Clean up the path
    $path = trim($requestUri, '/');
    $path = $path ?: 'home';
    
    // Log the request
    logTraffic($path);
    
    // Try to find and render the page
    renderPage($path);
}

// Main execution
try {
    initializeFramework();
    routeRequest();
} catch (Exception $e) {
    logError('Framework Error: ' . $e->getMessage());
    if (getenv('DEV_MODE') == '1') {
        echo '<h1>Framework Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Service Temporarily Unavailable</h1>';
        echo '<p>Please try again later.</p>';
    }
}
?>