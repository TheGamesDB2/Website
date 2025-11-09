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

// Check if token is valid
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;
$user_id = null;

if(!empty($token))
{
    $db = $tgdb_user->getDatabase();
    $stmt = $db->prepare("SELECT user_id, expires FROM password_reset_tokens WHERE token = :token AND used = 0");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($token_data && $token_data['expires'] > time())
    {
        $valid_token = true;
        $user_id = $token_data['user_id'];
    }
    else
    {
        $error_msgs[] = "Invalid or expired password reset token. Please request a new password reset link.";
    }
}
else
{
    $error_msgs[] = "Missing password reset token. Please request a new password reset link.";
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] == "POST" && $valid_token && empty($error_msgs) && empty($success_msg))
{
    if(!empty($_POST['password']) && !empty($_POST['confirm_password']))
    {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password match
        if($password !== $confirm_password)
        {
            $error_msgs[] = "Passwords do not match.";
        }
        else
        {
            // Reset the password using the token
            $result = $tgdb_user->resetPassword($token, $password);
            
            if($result['success'])
            {
                $success_msg[] = "Your password has been reset successfully. You can now <a href='login.php'>login</a> with your new password.";
            }
            else
            {
                $error_msgs[] = $result['message'];
            }
        }
    }
    else
    {
        $error_msgs[] = "Please enter and confirm your new password.";
    }
}

require_once __DIR__ . "/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Reset Password");
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
        <?php elseif($valid_token) : ?>
        <div class="row justify-content-center">
            <div class="card">
                <div class="card-header">
                    <fieldset>
                        <legend>Reset Password</legend>
                    </fieldset>
                </div>
                <div class="card-body">
                    <form id="reset_password_form" class="form-horizontal" method="post">
                        <div class="form-group">
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"> New Password </i></span>
                                </div>
                                <input class="form-control" name="password" id="password" placeholder="Enter your new password" type="password" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"> Confirm Password </i></span>
                                </div>
                                <input class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm your new password" type="password" required>
                            </div>
                        </div>

                        <div class="form-group ">
                            <button type="submit" class="btn btn-primary btn-lg btn-block">Set New Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php FOOTER::print(); ?>
