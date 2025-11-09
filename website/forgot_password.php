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
        
        // Request password reset
        $result = $tgdb_user->requestPasswordReset($email);
        
        if($result['success'])
        {
            if(isset($result['token']) && isset($result['user']))
            {
                // Create reset link
                $reset_link = CommonUtils::$WEBSITE_BASE_URL . "reset_password.php?token=" . $result['token'];
                
                // Send email with reset link
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
                    $success_msg[] = "Password reset instructions have been sent to your email address. Please check your inbox.";
                }
                else
                {
                    $error_msgs[] = "Failed to send password reset email. Please try again later.";
                }
            }
            else
            {
                // Don't reveal if email exists or not for security reasons
                $success_msg[] = "If your email address exists in our database, you will receive a password reset link at your email address in a few minutes.";
            }
        }
        else
        {
            $error_msgs[] = $result['message'];
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
