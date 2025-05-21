<?php
/**
 * Custom phpBB Registration Script
 *
 * IMPORTANT: Read all disclaimers. This is a simplified example and has security implications.
 * It is strongly recommended to use phpBB's native registration process.
 *
 * @version 1.0.7 (Debugging removed)
 * @author AI (Conceptual Example)
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
$registration_success = false;
$form_submitted = ($_SERVER['REQUEST_METHOD'] === 'POST');

// --- Form Data Repopulation ---
$form_values = [
    'username' => '',
    'email' => '',
];

if ($form_submitted) {
    // Store submitted values for repopulation in case of error
    $form_values['username'] = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
    $form_values['email']    = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';

    // --- Attempt to Load phpBB Environment ---
    $phpEx_to_use = isset($phpEx) ? $phpEx : 'php';
    $common_php_file = $phpbb_root_path . 'common.' . $phpEx_to_use;

    if (!file_exists($common_php_file)) {
        if ($phpEx_to_use !== 'php') { // Try 'php' if initial attempt failed
            $phpEx = 'php';
            $common_php_file = $phpbb_root_path . 'common.' . $phpEx;
        }
        if (!file_exists($common_php_file)) {
            // In a production environment, you might log this error instead of die()
            error_log('FATAL ERROR: phpBB common file (common.' . htmlspecialchars($phpEx) . ') not found. PHPBB_ROOT_PATH: ' . PHPBB_ROOT_PATH);
            die('A configuration error occurred. Please try again later.');
        }
    } else {
         if (!isset($phpEx)) { $phpEx = $phpEx_to_use; }
    }

    require($common_php_file);

    // --- Ensure user environment is fully set up ---
    if (isset($user) && is_object($user)) {
        if (empty($user->session_id)) {
            $user->session_begin();
        }
        $user->setup();
        $user->add_lang('ucp');
    } else {
        error_log('FATAL ERROR: $user object not available after including common.php.');
        die('A critical error occurred during registration setup. Please try again later.');
    }
    // --- End user environment setup ---

    // Ensure functions_user.php is loaded
    $functions_user_file = $phpbb_root_path . 'includes/functions_user.' . $phpEx;
    if (!file_exists($functions_user_file)) {
        error_log('FATAL ERROR: phpBB user functions file (includes/functions_user.' . htmlspecialchars($phpEx) . ') not found.');
        die('A critical error occurred. Please try again later.');
    }
    require($functions_user_file);

    // Ensure functions_validate.php is loaded if validate_data is not yet defined
    if (!function_exists('validate_data')) {
        $functions_validate_file = $phpbb_root_path . 'includes/functions_validate.' . $phpEx;
        if (file_exists($functions_validate_file)) {
            require($functions_validate_file);
        } else {
            error_log('FATAL ERROR: validate_data() function not found and includes/functions_validate.' . htmlspecialchars($phpEx) . ' also not found.');
            die('A critical validation error occurred. Please try again later.');
        }
    }

    // --- Retrieve Form Data using phpBB's request_var ---
    if (!isset($request) && isset($phpbb_container) && $phpbb_container->has('request')) {
        $request = $phpbb_container->get('request');
    } elseif (!isset($request)) {
        error_log('FATAL ERROR: phpBB $request object not available.');
        die('A critical error occurred processing your request. Please try again later.');
    }

    $username         = $request->variable('username', '', true);
    $password         = $request->variable('password', '', true);
    $password_confirm = $request->variable('password_confirm', '', true);
    $email            = strtolower($request->variable('email', ''));

    // --- Server-Side Validation ---
    if (empty($username) || empty($password) || empty($email)) {
        $error_messages[] = isset($user->lang['FIELDS_EMPTY']) ? $user->lang['FIELDS_EMPTY'] : 'All fields are required.';
    }
    if ($password !== $password_confirm) {
        $error_messages[] = isset($user->lang['NEW_PASSWORD_ERROR']) ? $user->lang['NEW_PASSWORD_ERROR'] : 'Passwords do not match.';
    }

    if (empty($error_messages)) {
        if (!function_exists('validate_data')) {
            // This should have been caught earlier, but as a safeguard:
            error_log('FATAL ERROR: validate_data() function still not found before validation.');
            die('A critical validation error occurred. Please try again later.');
        }

        // Username Validation
        $username_data_to_validate = ['username_field' => $username];
        $username_rules = [
            'username_field' => [
                ['string', false, (isset($config['min_name_chars']) ? (int)$config['min_name_chars'] : 3), (isset($config['max_name_chars']) ? (int)$config['max_name_chars'] : 20)],
                ['username']
            ]
        ];
        $username_validation_errors = validate_data($username_data_to_validate, $username_rules);
        if (!empty($username_validation_errors)) {
            foreach ($username_validation_errors as $err_key) {
                $error_messages[] = isset($user->lang[$err_key]) ? $user->lang[$err_key] : $err_key;
            }
        }

        // Email Validation
        if (empty($error_messages)) {
            $email_data_to_validate = ['email_field' => $email];
            $email_rules = [
                'email_field' => [
                    ['email']
                ]
            ];
            $email_validation_errors = validate_data($email_data_to_validate, $email_rules);
            if (!empty($email_validation_errors)) {
                foreach ($email_validation_errors as $err_key) {
                    $error_messages[] = isset($user->lang[$err_key]) ? $user->lang[$err_key] : $err_key;
                }
            }
        }

        // Password Length
        if (isset($config['min_pass_chars']) && utf8_strlen($password) < $config['min_pass_chars']) {
             $error_messages[] = sprintf( (isset($user->lang['PASSWORD_TOO_SHORT']) ? $user->lang['PASSWORD_TOO_SHORT'] : 'Password too short, min %d chars.'), $config['min_pass_chars']);
        }
    }

    // --- If Validation Passes, Attempt Registration ---
    if (empty($error_messages)) {
        // --- Explicitly hash the password using phpBB's password manager ---
        $hashed_password = '';
        if (isset($phpbb_container) && $phpbb_container->has('passwords.manager')) {
            /** @var \phpbb\passwords\manager $passwords_manager */
            $passwords_manager = $phpbb_container->get('passwords.manager');
            $hashed_password = $passwords_manager->hash($password);
        } else {
            $error_messages[] = 'Critical error: phpBB Password Manager service not available.';
            error_log('Critical error: phpBB Password Manager service not available during registration.');
        }
        // --- End password hashing ---

        if (empty($hashed_password) && empty($error_messages)) {
             $error_messages[] = 'Critical error: Password hashing failed.';
             error_log('Critical error: Password hashing failed during registration.');
        }

        if (empty($error_messages)) {
            $sql = 'SELECT group_id FROM ' . GROUPS_TABLE . " WHERE group_name = 'REGISTERED' AND group_type = " . GROUP_SPECIAL;
            $result = $db->sql_query($sql);
            $group_row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$group_row) {
                $error_messages[] = 'Critical error: Could not find REGISTERED user group.';
                error_log('Critical error: Could not find REGISTERED user group during registration.');
            } else {
                $user_row = [
                    'username'             => $username,
                    'user_password'        => $hashed_password,
                    'user_email'           => $email,
                    'group_id'             => (int) $group_row['group_id'],
                    'user_timezone'        => isset($config['board_timezone']) ? $config['board_timezone'] : 'UTC',
                    'user_lang'            => isset($user->data['user_lang']) && $user->data['user_lang'] ? $user->data['user_lang'] : (isset($config['default_lang']) ? $config['default_lang'] : 'en'),
                    'user_type'            => USER_NORMAL,
                    'user_ip'              => isset($user->ip) ? $user->ip : $_SERVER['REMOTE_ADDR'],
                    'user_regdate'         => time(),
                    'user_inactive_reason' => 0,
                    'user_inactive_time'   => 0,
                ];

                $user_id_or_error = user_add($user_row, false, false, true);

                if ($user_id_or_error === false || !is_int($user_id_or_error)) {
                    if (is_string($user_id_or_error) && isset($user->lang[$user_id_or_error])) {
                        $error_messages[] = $user->lang[$user_id_or_error];
                    } elseif (isset($user->error) && is_array($user->error) && !empty($user->error)) {
                         foreach ($user->error as $err_key) {
                            $error_messages[] = isset($user->lang[$err_key]) ? $user->lang[$err_key] : $err_key;
                         }
                    } else {
                        $error_messages[] = isset($user->lang['REGISTRATION_ERROR']) ? $user->lang['REGISTRATION_ERROR'] : 'An unknown error occurred during registration.';
                        if (is_string($user_id_or_error)) { error_log("Raw error from user_add: " . htmlspecialchars($user_id_or_error)); }
                    }
                } else {
                    $registration_success = true;
                    $success_message = (isset($user->lang['REG_SUCCESS']) ? $user->lang['REG_SUCCESS'] : 'Registration successful!') . ' User ID: ' . $user_id_or_error;
                    $form_values['username'] = '';
                    $form_values['email'] = '';
                }
            }
        }
    }
}

// --- Prepare Messages for Display ---
if (!empty($error_messages)) {
    $message_display = '<div class="message error"><strong>Errors:</strong><br />' . implode('<br />', array_map('htmlspecialchars', $error_messages)) . '</div>';
} elseif ($registration_success && isset($success_message)) {
    $message_display = '<div class="message success">' . htmlspecialchars($success_message) . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Forum Registration</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #1d2129; margin-bottom: 25px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #4b4f56; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 18px;
            border: 1px solid #ccd0d5;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus {
            border-color: #5b9dd9;
            box-shadow: 0 0 0 2px rgba(81,127,180,0.25);
            outline: none;
        }
        input[type="submit"] {
            background-color: #007bff; /* Standard blue */
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
        input[type="submit"]:hover { background-color: #0056b3; /* Darker blue */ }
        .message { padding: 12px 15px; margin-bottom: 20px; border-radius: 6px; font-size: 15px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .warning-box {
            padding: 12px 15px; margin-bottom: 20px; border-radius: 6px;
            background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;
        }
        .warning-box a { color: #0056b3; text-decoration: none; }
        .warning-box a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>New User Registration</h2>

        <?php echo $message_display; // Display errors or success message here ?>

        <?php if (!$registration_success): // Only show form if registration isn't successful yet ?>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="registrationForm">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form_values['username']); ?>" required 
                       minlength="<?php echo (isset($config['min_name_chars']) && $config['min_name_chars']) ? (int)$config['min_name_chars'] : 3; ?>" 
                       maxlength="<?php echo (isset($config['max_name_chars']) && $config['max_name_chars']) ? (int)$config['max_name_chars'] : 20; ?>">
            </div>
            <div>
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_values['email']); ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required 
                       minlength="<?php echo (isset($config['min_pass_chars']) && $config['min_pass_chars']) ? (int)$config['min_pass_chars'] : 6; ?>" 
                       maxlength="<?php echo (isset($config['max_pass_chars']) && $config['max_pass_chars']) ? (int)$config['max_pass_chars'] : 100; ?>">
            </div>
            <div>
                <label for="password_confirm">Confirm Password:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <div>
                <input type="submit" value="Register">
            </div>
        </form>
        <?php else: ?>
            <p style="text-align:center;">You can now <a href="/login.php">login to the website</a>.</p>
        <?php endif; ?>
    </div>
</body>
</html>

