<?php
require_once __DIR__ . "/include/login.common.class.php";
require_once __DIR__ . "/../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$tgdb_user = TGDBUser::getInstance();

// Check if user is already logged in
if($tgdb_user->isLoggedIn())
{
	$error_msgs[] = "You are already logged in. You will be automatically redirected, if it takes longer than 10 seconds <a href='" . CommonUtils::$WEBSITE_BASE_URL . "'>Click Here</a>." .
		'<script type="text/javascript">setTimeout(function(){window.location="' . CommonUtils::$WEBSITE_BASE_URL . '";}, 5000);</script>';
}

// Function to send verification email
function sendVerificationEmail($username, $email, $hash) {
    // This is a placeholder function that will be implemented later
    // It should send an email to the user with a verification link
    // The link should include the hash for verification
    
    // For now, we'll just return true to simulate success
    return true;
}

// Function to generate a hash from email
function generateEmailHash($email) {
    // Generate a unique hash based on the email address
    return md5($email . time());
}

// Function to validate the captcha
function validateCaptcha() {
    // This is a simple captcha validation
    // In a real implementation, you would use a service like reCAPTCHA
    if(isset($_POST['captcha']) && isset($_SESSION['captcha'])) {
        return strtolower($_POST['captcha']) === strtolower($_SESSION['captcha']);
    }
    return false;
}

// Generate a simple captcha code and store it in session
if(!isset($_SESSION['captcha'])) {
    $captcha_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    $_SESSION['captcha'] = $captcha_code;
}

// Process registration form submission
if($_SERVER['REQUEST_METHOD'] == "POST" && empty($error_msgs) && empty($success_msg))
{
    // Validate captcha first
    if(!validateCaptcha()) {
        $error_msgs[] = "Invalid captcha code. Please try again.";
    } else {
        // Validate form fields
        if(empty($_POST['username'])) {
            $error_msgs[] = "Username cannot be empty.";
        }
        
        if(empty($_POST['password'])) {
            $error_msgs[] = "Password cannot be empty.";
        }
        
        if(empty($_POST['confirm_password'])) {
            $error_msgs[] = "Please confirm your password.";
        } elseif($_POST['password'] !== $_POST['confirm_password']) {
            $error_msgs[] = "Passwords do not match.";
        }
        
        if(empty($_POST['email'])) {
            $error_msgs[] = "Email address cannot be empty.";
        } elseif(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $error_msgs[] = "Please enter a valid email address.";
        }
        
        // If no errors, proceed with registration
        if(empty($error_msgs)) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            $email = $_POST['email'];
            $hash = generateEmailHash($email);
            $created_at = date('Y-m-d H:i:s');
            
            try {
                // Begin transaction
                $db = $tgdb_user->getDatabase();
                $db->beginTransaction();
                
                // Check if username already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                if($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $error_msgs[] = "Username already exists. Please choose a different username.";
                } else {
                    // Check if email already exists
                    $stmt = $db->prepare("SELECT id FROM users WHERE email_address = :email");
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    
                    if($stmt->fetch(PDO::FETCH_ASSOC)) {
                        $error_msgs[] = "Email address already in use. Please use a different email address.";
                    } else {
                        // Hash the password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert the new user
                        $stmt = $db->prepare("INSERT INTO users (username, password, email_address, created_at, hashed) 
                                             VALUES (:username, :password, :email, :created_at, :hash)");
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':created_at', $created_at);
                        $stmt->bindParam(':hash', $hash);
                        $stmt->execute();
                        
                        $user_id = $db->lastInsertId();
                        
                        // Commit the transaction
                        $db->commit();
                        
                        // Send verification email
                        if(sendVerificationEmail($username, $email, $hash)) {
                            $success_msg[] = "Registration successful! Please check your email to verify your account. You will be redirected to the login page in 10 seconds. <a href='login.php'>Click here</a> if you are not redirected automatically.<br> If you do not receive the email, please reach out to us on <a class=\"nav-link\" href=\"https://discord.gg/2gxeAURxmA\">Discord</a>" .
                                '<script type="text/javascript">setTimeout(function(){window.location="login.php";}, 10000);</script>';
                            
                            // Generate a new captcha for next use
                            $captcha_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                            $_SESSION['captcha'] = $captcha_code;
                        } else {
                            $error_msgs[] = "Registration successful but failed to send verification email. Please contact support.";
                        }
                    }
                }
            } catch(PDOException $e) {
                $db->rollBack();
                $error_msgs[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . "/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Register");
$Header->appendRawHeader(function() { global $Game; ?>

	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.10/css/all.css"
	integrity="sha384-+d0P83n9kaQMCwj8F4RJB66tzIwOKmrdb46+porD/OvrJ+37WqIM7UoBtwHO6Nlg" crossorigin="anonymous">
    
    <style>
        .captcha-container {
            margin-bottom: 15px;
        }
        .captcha-box {
            background-color: #f0f0f0;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 10px;
            user-select: none;
        }
    </style>

<?php });?>
<?= $Header->print(); ?>

	<div class="container">
	<?php if(!empty($error_msgs)) : ?>
		<div class="row justify-content-center">
			<div class="alert alert-warning">
				<h4 class="alert-heading">Registration Failed!</h4>
				<?php foreach($error_msgs as $msg) : ?>
				<p class="mb-0"><?= $msg ?></p>
				<?php endforeach;?>
			</div>
		</div>
		<?php endif; ?>
		<?php if(!empty($success_msg)) : ?>
		<div class="row justify-content-center">
			<div class="alert alert-success">
				<h4 class="alert-heading">Registration Successful!</h4>
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
						<legend>Register</legend>
					</fieldset>
				</div>
				<div class="card-body">
					<form id="register_form" class="form-horizontal" method="post">
                        <div class="form-group">
							<div class="input-group mb-3">
								<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-users" aria-hidden="true"> Username </i></span>
								</div>
								<input class="form-control" name="username" id="username" placeholder="Enter your Username" type="text" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
							</div>
						</div>

						<div class="form-group">
							<div class="input-group mb-3">
								<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"> Password </i></span>
								</div>
								<input class="form-control" name="password" id="password" placeholder="Enter your Password" type="password">
							</div>
						</div>
                        
                        <div class="form-group">
							<div class="input-group mb-3">
								<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"> Confirm Password </i></span>
								</div>
								<input class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm your Password" type="password">
							</div>
						</div>
                        
                        <div class="form-group">
							<div class="input-group mb-3">
								<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-envelope" aria-hidden="true"> Email </i></span>
								</div>
								<input class="form-control" name="email" id="email" placeholder="Enter your Email Address" type="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
							</div>
						</div>
                        
                        <div class="form-group captcha-container">
                            <label>Please enter the code below:</label>
                            <div class="captcha-box">
                                <?= $_SESSION['captcha'] ?>
                            </div>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-shield-alt" aria-hidden="true"> Captcha </i></span>
                                </div>
                                <input class="form-control" name="captcha" id="captcha" placeholder="Enter the code above" type="text">
                            </div>
                        </div>

						<div class="form-group ">
							<button type="submit" class="btn btn-primary btn-lg btn-block">Register</button>
						</div>
						<div class="login-register">
							Already have an account? Login <a href="login.php">here</a>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>

<?php FOOTER::print(); ?>
