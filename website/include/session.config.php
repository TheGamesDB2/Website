<?php
/**
 * Session configuration file to enable cross-subdomain session sharing
 * This file should be included before starting any session
 */

// Only configure session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    // Extract the base domain from the current hostname
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    
    // Extract the base domain (e.g., thegamesdb.net from dev.thegamesdb.net or apidev.thegamesdb.net)
    $domain_parts = explode('.', $domain);
    
    if (count($domain_parts) > 2) {
        // Get the last two parts (e.g., thegamesdb.net)
        $base_domain = $domain_parts[count($domain_parts) - 2] . '.' . $domain_parts[count($domain_parts) - 1];
    } else {
        $base_domain = $domain;
    }
    
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
    
    // For PHP versions < 7.3
    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(
            $cookie_params['lifetime'],
            $cookie_params['path'],
            $cookie_params['domain'],
            $cookie_params['secure'],
            $cookie_params['httponly']
        );
    } else {
        // For PHP 7.3+
        session_set_cookie_params($cookie_params);
    }
    
    // Set the session name to be consistent
    session_name('TGDBSESSID');
    
    // Start the session
    session_start();
}

?>
