<?php
require_once __DIR__ . "/include/login.common.class.php";
require_once __DIR__ . "/../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$tgdb_user = TGDBUser::getInstance();

// Check if hash parameter exists
if(!isset($_GET['hash']) || empty($_GET['hash'])) {
    $error_msgs[] = "Invalid verification link. Please check your email and try again.";
} else {
    $hash = $_GET['hash'];
    
    try {
        // Get database connection
        $db = $tgdb_user->getDatabase();
        
        // Begin transaction
        $db->beginTransaction();
        
        // Find user with the provided hash
        $stmt = $db->prepare("SELECT id, username, email_address FROM users WHERE hashed = :hash");
        $stmt->bindParam(':hash', $hash);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user) {
            $error_msgs[] = "Invalid verification code or account already verified. Please check your email or contact support.";
        } else {
            // Get user ID
            $user_id = $user['id'];
            
            // Check if VALID_USER permission exists
            $stmt = $db->prepare("SELECT id FROM permissions WHERE permission_text = 'VALID_USER'");
            $stmt->execute();
            $permission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$permission) {
                // If the permission doesn't exist, create it
                $stmt = $db->prepare("INSERT INTO permissions (permission_text) VALUES ('VALID_USER')");
                $stmt->execute();
                $permission_id = $db->lastInsertId();
            } else {
                $permission_id = $permission['id'];
            }
            
            // Check if user already has this permission
            $stmt = $db->prepare("SELECT COUNT(*) as has_perm FROM users_permissions 
                                 WHERE users_id = :user_id AND permissions_id = :permission_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':permission_id', $permission_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($result['has_perm'] > 0) {
                $error_msgs[] = "Your account has already been verified. You can now <a href='login.php'>login</a>.";
            } else {
                // Assign the VALID_USER permission to the user
                $stmt = $db->prepare("INSERT INTO users_permissions (users_id, permissions_id) VALUES (:user_id, :permission_id)");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':permission_id', $permission_id);
                $stmt->execute();
                
                // Get the ADD_GAME permission ID
                $stmt = $db->prepare("SELECT id FROM permissions WHERE permission_text = 'ADD_GAME'");
                $stmt->execute();
                $permission = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$permission) {
                    // If the permission doesn't exist, create it
                    $stmt = $db->prepare("INSERT INTO permissions (permission_text) VALUES ('ADD_GAME')");
                    $stmt->execute();
                    $add_game_permission_id = $db->lastInsertId();
                } else {
                    $add_game_permission_id = $permission['id'];
                }
                
                // Check if user already has ADD_GAME permission
                $stmt = $db->prepare("SELECT COUNT(*) as has_perm FROM users_permissions 
                                     WHERE users_id = :user_id AND permissions_id = :permission_id");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':permission_id', $add_game_permission_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($result['has_perm'] == 0) {
                    // Assign the ADD_GAME permission to the user if they don't have it
                    $stmt = $db->prepare("INSERT INTO users_permissions (users_id, permissions_id) VALUES (:user_id, :permission_id)");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':permission_id', $add_game_permission_id);
                    $stmt->execute();
                }
                
                // Clear the hashed column after verification to prevent reuse of the verification link
                $stmt = $db->prepare("UPDATE users SET hashed = '' WHERE id = :user_id");

                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                
                // Commit the transaction
                $db->commit();
                
                $success_msg[] = "Your account has been successfully verified! You can now <a href='login.php'>login</a> to your account. You will be redirected to the login page in 10 seconds." .
                    '<script type="text/javascript">setTimeout(function(){window.location="login.php";}, 10000);</script>';
            }
        }
    } catch(PDOException $e) {
        // Rollback the transaction in case of error
        if(isset($db)) {
            $db->rollBack();
        }
        $error_msgs[] = "Database error: " . $e->getMessage();
    }
}

require_once __DIR__ . "/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Account Verification");
$Header->appendRawHeader(function() { global $Game; ?>

	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.10/css/all.css"
	integrity="sha384-+d0P83n9kaQMCwj8F4RJB66tzIwOKmrdb46+porD/OvrJ+37WqIM7UoBtwHO6Nlg" crossorigin="anonymous">

<?php });?>
<?= $Header->print(); ?>

	<div class="container">
	<?php if(!empty($error_msgs)) : ?>
		<div class="row justify-content-center">
			<div class="alert alert-warning">
				<h4 class="alert-heading">Verification Failed!</h4>
				<?php foreach($error_msgs as $msg) : ?>
				<p class="mb-0"><?= $msg ?></p>
				<?php endforeach;?>
			</div>
		</div>
		<?php endif; ?>
		<?php if(!empty($success_msg)) : ?>
		<div class="row justify-content-center">
			<div class="alert alert-success">
				<h4 class="alert-heading">Verification Successful!</h4>
				<?php foreach($success_msg as $msg) : ?>
				<p class="mb-0"><?= $msg ?></p>
				<?php endforeach;?>
			</div>
		</div>
		<?php else : ?>
        <?php if(empty($error_msgs)) : ?>
		<div class="row justify-content-center">
			<div class="card">
				<div class="card-header">
					<fieldset>
						<legend>Account Verification</legend>
					</fieldset>
				</div>
				<div class="card-body">
					<div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-3">Verifying your account...</p>
                    </div>
				</div>
			</div>
		</div>
        <?php endif; ?>
		<?php endif; ?>
	</div>

<?php FOOTER::print(); ?>
