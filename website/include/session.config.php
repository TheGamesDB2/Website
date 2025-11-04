<?php
/**
 * Session configuration file to enable cross-subdomain session sharing
 * This file should be included before starting any session
 */

// Create a debug log function
function session_debug_log($message, $data = null) {
    // Uncomment the line below to log to a file instead of output
    // file_put_contents(__DIR__ . '/../session_debug.log', date('[Y-m-d H:i:s] ') . $message . ($data !== null ? ': ' . print_r($data, true) : '') . "\n", FILE_APPEND);
    
    // For now, we'll just store debug info in a global array that can be displayed later
    if (!isset($GLOBALS['session_debug'])) {
        $GLOBALS['session_debug'] = [];
    }
    $GLOBALS['session_debug'][] = $message . ($data !== null ? ': ' . print_r($data, true) : '');
}

// Log initial state
session_debug_log('Session configuration started');
session_debug_log('Initial session status', session_status());
session_debug_log('Initial session ID', session_id());

// Only configure session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_debug_log('Configuring new session');
    
    // Extract the base domain from the current hostname
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    session_debug_log('Original domain', $domain);
    
    // Extract the base domain (e.g., thegamesdb.net from dev.thegamesdb.net or apidev.thegamesdb.net)
    $domain_parts = explode('.', $domain);
    session_debug_log('Domain parts', $domain_parts);
    
    if (count($domain_parts) > 2) {
        // Get the last two parts (e.g., thegamesdb.net)
        $base_domain = $domain_parts[count($domain_parts) - 2] . '.' . $domain_parts[count($domain_parts) - 1];
    } else {
        $base_domain = $domain;
    }
    session_debug_log('Base domain for cookies', $base_domain);
    
    // Set cookie parameters for session
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; // Use secure cookies if HTTPS
    $httponly = true; // Protect cookie from JavaScript access
    
    // Set session cookie parameters
    $cookie_params = [
        'lifetime' => 0, // Until browser is closed
        'path' => '/',
        'domain' => '.' . $base_domain, // Note the leading dot to include all subdomains
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax' // Allows the cookie to be sent when navigating to the site
    ];
    session_debug_log('Setting cookie params', $cookie_params);
    
    // For PHP versions < 7.3
    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(
            $cookie_params['lifetime'],
            $cookie_params['path'],
            $cookie_params['domain'],
            $cookie_params['secure'],
            $cookie_params['httponly']
        );
        session_debug_log('Using legacy session_set_cookie_params');
    } else {
        // For PHP 7.3+
        session_set_cookie_params($cookie_params);
        session_debug_log('Using modern session_set_cookie_params');
    }
    
    // Set the session name to be consistent
    session_name('TGDBSESSID');
    session_debug_log('Session name set to', session_name());
    
    // Start the session
    session_start();
    session_debug_log('Session started', session_id());
} else {
    session_debug_log('Session already active', session_id());
}

// Make debug info available
$_SESSION['session_debug'] = isset($_SESSION['session_debug']) ? 
    array_merge($_SESSION['session_debug'], $GLOBALS['session_debug']) : 
    $GLOBALS['session_debug'];

?>
