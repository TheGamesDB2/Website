<?php
/**
 * Custom phpBB Change Password Script (Direct DB Update Method)
 *
 * IMPORTANT: This script assumes the user is already logged into phpBB.
 * It attempts to verify the current password before updating the database directly.
 * Using phpBB's built-in UCP is generally more secure and recommended.
 *
 * @version 1.0.6
 * @author AI (Conceptual Example, adapted from user input)
 */

// --- Essential Configuration ---
define('PHPBB_ROOT_PATH', '../../forums.thegamesdb.net/'); // <<< IMPORTANT: Set this correctly!

// --- Standard phpBB Definitions ---
define('IN_PHPBB', true);

// --- Global variables that phpBB expects or uses ---
global $phpbb_root_path, $phpEx, $user, $auth, $cache, $db, $config, $template, $request, $symfony_request, $phpbb_container;

$phpbb_root_path = PHPBB_ROOT_PATH;
// $phpEx = 'php'; // Default, will be set by common.php

// --- Initialize Variables ---
$message_display = ''; // For user feedback
$error_messages = [];  // To store validation or process errors
$success_message_text = '';
$form_submitted = false; // Will be set later using $request

// --- Attempt to Load phpBB Environment ---
$phpEx_to_use = isset($phpEx) ? $phpEx : 'php';
$common_php_file = $phpbb_root_path . 'common.' . $phpEx_to_use;

if (!file_exists($common_php_file)) {
    if ($phpEx_to_use !== 'php') {
        $phpEx = 'php';
        $common_php_file = $phpbb_root_path . 'common.' . $phpEx;
    }
    if (!file_exists($common_php_file)) {
        error_log('FATAL ERROR: phpBB common file (common.' . htmlspecialchars($phpEx) . ') not found. PHPBB_ROOT_PATH: ' . PHPBB_ROOT_PATH);
        die('A configuration error occurred. Please try again later.');
    }
} else {
     if (!isset($phpEx)) { $phpEx = $phpEx_to_use; }
}

require($common_php_file);

// --- Start session and setup user ---
if (isset($user) && is_object($user)) {
    $user->session_begin();
    $user->setup('ucp');
} else {
    error_log('FATAL ERROR: $user object not available after including common.php for change_password.php.');
    die('A critical error occurred. Please try again later.');
}

// --- Initialize $request object (must be done after common.php) ---
if (!isset($request) && isset($phpbb_container) && $phpbb_container->has('request')) {
    $request = $phpbb_container->get('request');
} elseif (!isset($request)) {
    error_log('FATAL ERROR: phpBB $request object not available for change_password.php.');
    die('A critical error occurred processing your request. Please try again later.');
}
$form_submitted = $request->is_set_post('current_password');

// --- Check if user is logged in ---
if (!$user->data['is_registered'] || $user->data['user_id'] == ANONYMOUS) {
    $error_messages[] = isset($user->lang['LOGIN_REQUIRED_CHANGE_PASSWORD']) ? $user->lang['LOGIN_REQUIRED_CHANGE_PASSWORD'] : 'You must be logged in to change your password.';
} else {
    if ($form_submitted) {
        $current_password   = $request->variable('current_password', '', true);
        $new_password       = $request->variable('new_password', '', true);
        $new_password_confirm = $request->variable('new_password_confirm', '', true);

        // --- Validate input ---
        if (empty($current_password) || empty($new_password) || empty($new_password_confirm)) {
            $error_messages[] = isset($user->lang['ALL_FIELDS_REQUIRED']) ? $user->lang['ALL_FIELDS_REQUIRED'] : 'All password fields are required.';
        }
        if ($new_password !== $new_password_confirm) {
            $error_messages[] = isset($user->lang['NEW_PASSWORD_ERROR']) ? $user->lang['NEW_PASSWORD_ERROR'] : 'The new passwords do not match.';
        }
        if (isset($config['min_pass_chars']) && utf8_strlen($new_password) < $config['min_pass_chars']) {
            $error_messages[] = sprintf( (isset($user->lang['PASSWORD_TOO_SHORT']) ? $user->lang['PASSWORD_TOO_SHORT'] : 'New password is too short, min %d chars.'), $config['min_pass_chars']);
        }

        // --- Verify current password ---
        if (empty($error_messages)) {
            if (!isset($phpbb_container) || !$phpbb_container->has('passwords.manager')) {
                $error_messages[] = 'Password change service is currently unavailable. Please try again later or contact an administrator.';
                error_log('CRITICAL: phpBB Password Manager service (passwords.manager) not available during password change attempt.');
            } else {
                /** @var \phpbb\passwords\manager $passwords_manager */
                $passwords_manager = $phpbb_container->get('passwords.manager');
                $user_password_hash_from_db = $user->data['user_password'];

                if (!$passwords_manager->check($current_password, $user_password_hash_from_db)) {
                    $error_messages[] = isset($user->lang['CURRENT_PASSWORD_INVALID']) ? $user->lang['CURRENT_PASSWORD_INVALID'] : 'Your current password was not entered correctly.';
                }
            }
        }

        // --- If all checks pass, update the password using direct DB method ---
        if (empty($error_messages)) {
            if (!function_exists('phpbb_hash')) {
                error_log('FATAL ERROR: phpbb_hash() function not found. This function is required for the direct DB password update method.');
                $error_messages[] = 'A critical error occurred (hashing function missing). Please contact an administrator.';
            } else {
                $new_password_hashed_for_db = phpbb_hash($new_password);

                $sql_array = array(
                    'user_password'   => $new_password_hashed_for_db,
                    'user_passchg'    => time(),
                );

                $sql = 'UPDATE ' . USERS_TABLE . '
                        SET ' . $db->sql_build_array('UPDATE', $sql_array) . '
                        WHERE user_id = ' . (int)$user->data['user_id'];
                
                $db->sql_query($sql);

                // Check for SQL errors - User has requested this specific line to be commented out.
                // $sql_error_details = $db->sql_error(); 
                // The following logic would depend on $sql_error_details, so it's effectively disabled too.
                // For a production system, robust SQL error checking is recommended here.
                // if (!empty($sql_error_details['message'])) {
                //      $error_messages[] = isset($user->lang['UCP_PASSWORD_CHANGE_FAILED']) ? $user->lang['UCP_PASSWORD_CHANGE_FAILED'] : 'An error occurred while updating the database.';
                //      error_log('SQL Error during password update: Code ' . $sql_error_details['code'] . ' - ' . $sql_error_details['message']);
                // } else {
                    $success_message_text = isset($user->lang['PASSWORD_UPDATED']) ? $user->lang['PASSWORD_UPDATED'] : 'Your password has been successfully updated.';
                    $user->data['user_password'] = $new_password_hashed_for_db;
                // }
            }
        }
    }
}


// --- Prepare Messages for Display ---
if (!empty($error_messages)) {
    $message_display = '<div class="message error"><strong>Errors:</strong><br />' . implode('<br />', array_map('htmlspecialchars', $error_messages)) . '</div>';
} elseif (!empty($success_message_text)) {
    $message_display = '<div class="message success">' . htmlspecialchars($success_message_text) . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Forum Password</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #1d2129; margin-bottom: 25px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #4b4f56; }
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 18px;
            border: 1px solid #ccd0d5;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input[type="password"]:focus {
            border-color: #5b9dd9;
            box-shadow: 0 0 0 2px rgba(81,127,180,0.25);
            outline: none;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.2s ease-in-out;
        }
        input[type="submit"]:hover { background-color: #0056b3; }
        .message { padding: 12px 15px; margin-bottom: 20px; border-radius: 6px; font-size: 15px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Change Forum Password</h2>

        <?php echo $message_display; // Display errors or success message here ?>

        <?php if ($user->data['is_registered'] && $user->data['user_id'] != ANONYMOUS): // Only show form if user is logged in ?>
            <?php if (empty($success_message_text)): // Don't show form again if password was just successfully changed ?>
            <form action="<?php echo htmlspecialchars(append_sid($request->server('PHP_SELF'))); ?>" method="post" id="changePasswordForm">
                <div>
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div>
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required 
                           minlength="<?php echo (isset($config['min_pass_chars']) && $config['min_pass_chars']) ? (int)$config['min_pass_chars'] : 6; ?>">
                </div>
                <div>
                    <label for="new_password_confirm">Confirm New Password:</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" required>
                </div>
                <div>
                    <input type="submit" value="Change Password">
                </div>
            </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="message info">
                Please <a href="/login.php">login</a> to change your password.
            </div>
        <?php endif; ?>
         <p style="text-align:center; margin-top: 20px;">
            <a href="/">Return to the Home Page</a>
        </p>
    </div>
</body>
</html>

