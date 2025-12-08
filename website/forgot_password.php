<?php
require_once __DIR__ . "/include/login.common.class.php";
require_once __DIR__ . "/../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$_user = phpBBUser::getInstance();
$tgdb_user = TGDBUser::getInstance();

// If user is already logged in, redirect to home page
if($_user->isLoggedIn() || $tgdb_user->isLoggedIn())
{
    $error_msgs[] = "User is already logged in. You will be automatically redirected, if it takes longer than 10 seconds <a href='" . CommonUtils::$WEBSITE_BASE_URL . "'>Click Here</a>." .
        '<script type="text/javascript">setTimeout(function(){window.location="' . CommonUtils::$WEBSITE_BASE_URL . '";}, 5000);</script>';
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] == "POST" && empty($error_msgs) && empty($success_msg))
{
    if(!empty($_POST['email']))
    {
        $email = trim($_POST['email']);
        
        // new forgot password: original TGDB-only password reset flow commented out below
        // $result = $tgdb_user->requestPasswordReset($email);
        // 
        // if($result['success'])
        // {
        //     if(isset($result['token']) && isset($result['user']))
        //     {
        //         // Create reset link
        //         $reset_link = CommonUtils::$WEBSITE_BASE_URL . "reset_password.php?token=" . $result['token'];
        //         
        //         // Send email with reset link
        //         $to = $email;
        //         $subject = "Password Reset Request - TheGamesDB";
        //         $message = "Hello " . $result['user']['username'] . ",\n\n";
        //         $message .= "You have requested to reset your password. Please click the link below to set a new password:\n\n";
        //         $message .= $reset_link . "\n\n";
        //         $message .= "This link will expire in 1 hour.\n\n";
        //         $message .= "If you did not request this password reset, please ignore this email.\n\n";
        //         $message .= "Regards,\nTheGamesDB Team";
        //         $headers = "From: noreply@thegamesdb.net";
        //         
        //         if(mail($to, $subject, $message, $headers))
        //         {
        //             $success_msg[] = "Password reset instructions have been sent to your email address. Please check your inbox.";
        //         }
        //         else
        //         {
        //             $error_msgs[] = "Failed to send password reset email. Please try again later.";
        //         }
        //     }
        //     else
        //     {
        //         // Don't reveal if email exists or not for security reasons
        //         $success_msg[] = "If your email address exists in our database, you will receive a password reset link at your email address in a few minutes.";
        //     }
        // }
        // else
        // {
        //     $error_msgs[] = $result['message'];
        // }

        // new forgot password: extended flow to migrate phpBB-only users into TGDB before requesting reset

        // new forgot password: first, check if a TGDB user already exists for this email
        try
        {
            $db = $tgdb_user->getDatabase(); // new forgot password: reuse TGDB PDO connection

            $stmt = $db->prepare("SELECT id, username, email_address FROM users WHERE email_address = :email"); // new forgot password: check TGDB users table by email
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $existing_tgdb_user = $stmt->fetch(PDO::FETCH_ASSOC); // new forgot password: TGDB user row if it exists

            if(!$existing_tgdb_user)
            {
                // new forgot password: no TGDB user, attempt to find a phpBB user with this email and migrate them
                global $db, $table_prefix; // new forgot password: phpBB database globals from included common.php

                if(isset($db) && isset($table_prefix))
                {
                    $phpbb_sql = 'SELECT user_id, username, user_email FROM ' . $table_prefix . "users WHERE user_email = '" . $db->sql_escape($email) . "'"; // new forgot password: lookup phpBB user by email
                    $phpbb_result = $db->sql_query($phpbb_sql); // new forgot password: execute phpBB query
                    $phpbb_user = $db->sql_fetchrow($phpbb_result); // new forgot password: fetch phpBB user row
                    $db->sql_freeresult($phpbb_result); // new forgot password: free phpBB result

                    if($phpbb_user)
                    {
                        // new forgot password: phpBB user exists, create a corresponding TGDB user with a random password
                        $random_password = bin2hex(random_bytes(8)); // new forgot password: temporary password, user will set a new one via reset

                        $create_res = $tgdb_user->createUser($phpbb_user['username'], $random_password, $phpbb_user['user_email']); // new forgot password: create TGDB user from phpBB data

                        if(!$create_res['success'])
                        {
                            $error_msgs[] = "Unable to create local account for password reset. Please try again later."; // new forgot password: surface migration error
                        }
                    }
                }
            }
        }
        catch(Exception $e)
        {
            // new forgot password: on any unexpected error during migration, continue with generic behavior below
        }

        // new forgot password: now request TGDB password reset (user may have just been created from phpBB)
        $result = $tgdb_user->requestPasswordReset($email);
        
        if($result['success'])
        {
            if(isset($result['token']) && isset($result['user']))
            {
                // Create reset link (new forgot password: unchanged behavior, but now works for migrated users too)
                $reset_link = CommonUtils::$WEBSITE_BASE_URL . "reset_password.php?token=" . $result['token'];
                
                // Send email with reset link (new forgot password)
                $to = $email;
                $subject = "Password Reset Request - TheGamesDB";
                $message = "Hello " . $result['user']['username'] . ",\n\n";
                $message .= "You have requested to reset your password. Please click the link below to set a new password:\n\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you did not request this password reset, please ignore this email.\n\n";
                $message .= "Regards,\nTheGamesDB Team";
                $headers = "From: noreply@thegamesdb.net";
                
                if(mail($to, $subject, $message, $headers))
                {
                    $success_msg[] = "Password reset instructions have been sent to your email address. Please check your inbox."; // new forgot password
                }
                else
                {
                    $error_msgs[] = "Failed to send password reset email. Please try again later."; // new forgot password
                }
            }
            else
            {
                // Don't reveal if email exists or not for security reasons (new forgot password: same behavior as before)
                $success_msg[] = "If your email address exists in our database, you will receive a password reset link at your email address in a few minutes."; // new forgot password
            }
        }
        else
        {
            $error_msgs[] = $result['message']; // new forgot password
        }
    }
    else
    {
        $error_msgs[] = "Please enter your email address.";
    }
}

require_once __DIR__ . "/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Forgot Password");
$Header->appendRawHeader(function() { global $Game; ?>

    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.10/css/all.css"
    integrity="sha384-+d0P83n9kaQMCwj8F4RJB66tzIwOKmrdb46+porD/OvrJ+37WqIM7UoBtwHO6Nlg" crossorigin="anonymous">

<?php });?>
<?= $Header->print(); ?>

    <div class="container">
    <?php if(!empty($error_msgs)) : ?>
        <div class="row justify-content-center">
            <div class="alert alert-warning">
                <h4 class="alert-heading">Action Failed!</h4>
                <?php foreach($error_msgs as $msg) : ?>
                <p class="mb-0"><?= $msg ?></p>
                <?php endforeach;?>
            </div>
        </div>
        <?php endif; ?>
        <?php if(!empty($success_msg)) : ?>
        <div class="row justify-content-center">
            <div class="alert alert-success">
                <h4 class="alert-heading">Action Completed!</h4>
                <?php foreach($success_msg as $msg) : ?>
                <p class="mb-0"><?= $msg ?></p>
                <?php endforeach;?>
            </div>
        </div>
        <?php else : ?>
        <div class="row justify-content-center">
            <div class="card">
                <div class="card-header">
                    <fieldset>
                        <legend>Forgot Password</legend>
                    </fieldset>
                </div>
                <div class="card-body">
                    <form id="forgot_password_form" class="form-horizontal" method="post">
                        <div class="form-group">
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-envelope" aria-hidden="true"> Email </i></span>
                                </div>
                                <input class="form-control" name="email" id="email" placeholder="Enter your Email Address" type="email" required>
                            </div>
                        </div>

                        <div class="form-group ">
                            <button type="submit" class="btn btn-primary btn-lg btn-block">Reset Password</button>
                        </div>
                        <div class="login-register">
                            Remember your password? <a href="login.php">Login here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php FOOTER::print(); ?>
