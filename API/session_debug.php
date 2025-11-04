<?php
// Include session configuration
require_once __DIR__ . "/../include/session.config.php";

// Function to display session information
function displaySessionInfo() {
    echo "<h1>Session Debug Information</h1>";
    echo "<pre>";
    
    // Basic session info
    echo "Session Name: " . session_name() . "\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Not Active") . "\n\n";
    
    // Cookie parameters
    echo "Session Cookie Parameters:\n";
    print_r(session_get_cookie_params());
    
    // Server information
    echo "\nServer Information:\n";
    echo "HTTP_HOST: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Not set') . "\n";
    echo "SERVER_NAME: " . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'Not set') . "\n";
    echo "REQUEST_URI: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Not set') . "\n";
    echo "SCRIPT_NAME: " . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'Not set') . "\n";
    
    // Session data
    echo "\nSession Data:\n";
    print_r($_SESSION);
    
    // Cookies
    echo "\nCookies:\n";
    print_r($_COOKIE);
    
    // Debug log from session.config.php
    if (isset($_SESSION['session_debug'])) {
        echo "\nSession Configuration Debug Log:\n";
        foreach ($_SESSION['session_debug'] as $log_entry) {
            echo "- " . $log_entry . "\n";
        }
    }
    
    echo "</pre>";
}

// Check if we should set a test value
if (isset($_GET['set'])) {
    $_SESSION['test_value'] = $_GET['set'];
    echo "<p>Set test_value to: " . htmlspecialchars($_GET['set']) . "</p>";
}

// Display header
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
        .actions { margin: 20px 0; padding: 10px; background: #eef; border: 1px solid #ccf; }
        .domain-info { margin: 20px 0; padding: 10px; background: #efe; border: 1px solid #cfc; }
    </style>
</head>
<body>
    <div class="domain-info">
        <h2>Cross-Domain Session Test</h2>
        <p>Current domain: <strong><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></strong></p>
        <p>To test cross-domain sessions:</p>
        <ol>
            <li>Visit this page on the main domain (dev.thegamesdb.net)</li>
            <li>Set a test value using the form below</li>
            <li>Then visit the same page on the API domain (apidev.thegamesdb.net)</li>
            <li>Check if the test value is visible in the Session Data section</li>
        </ol>
    </div>
    
    <div class="actions">
        <h3>Session Test Actions</h3>
        <form method="get">
            <label>Set test value in session: 
                <input type="text" name="set" value="test-<?= time() ?>">
            </label>
            <button type="submit">Set Value</button>
        </form>
        
        <p>
            <a href="?">Refresh</a> | 
            <a href="?clear=1">Clear Session</a>
        </p>
        
        <?php if (isset($_GET['clear'])): ?>
            <?php 
            // Clear session
            session_unset();
            session_destroy();
            echo "<p>Session cleared!</p>";
            ?>
        <?php endif; ?>
    </div>

    <?php displaySessionInfo(); ?>
    
    <div class="actions">
        <h3>Links to Test</h3>
        <ul>
            <li><a href="http://dev.thegamesdb.net/session_debug.php" target="_blank">Main Site Session Debug</a></li>
            <li><a href="http://apidev.thegamesdb.net/session_debug.php" target="_blank">API Site Session Debug</a></li>
            <li><a href="http://dev.thegamesdb.net/login.php" target="_blank">Login Page</a></li>
            <li><a href="http://apidev.thegamesdb.net/key.php" target="_blank">API Key Page</a></li>
        </ul>
    </div>
</body>
</html>
