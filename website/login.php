<?php
require_once __DIR__ . "/include/login.common.class.php";
require_once __DIR__ . "/../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$_user = phpBBUser::getInstance();
$tgdb_user = TGDBUser::getInstance();
if(isset($_REQUEST['logout']))
{
	$tgdb_user->Logout();

		$success_msg[] = "User logged out successfully. You will be automatically redirected, if it takes longer than 10 seconds <a href='" . CommonUtils::$WEBSITE_BASE_URL . "'>Click Here</a>." .
		'<script type="text/javascript">setTimeout(function(){window.location="' . CommonUtils::$WEBSITE_BASE_URL . '";}, 5000);</script>';
	
}
else if($_user->isLoggedIn())
{
	$error_msgs[] = "User is already logged in. You will be automatically redirected, if it takes longer than 10 seconds <a href='" . CommonUtils::$WEBSITE_BASE_URL . "'>Click Here</a>." .
		'<script type="text/javascript">setTimeout(function(){window.location="' . CommonUtils::$WEBSITE_BASE_URL . '";}, 5000);</script>';
}

if($_SERVER['REQUEST_METHOD'] == "POST" && empty($error_msgs) && empty($success_msg))
{
	if(!$tgdb_user->isLoggedIn())
	{
		if(!empty($_POST['username']) && !empty($_POST['password']))
		{
			$tgdb_res = $tgdb_user->Login(false,false);

			if($tgdb_res['status'] != "LOGIN_SUCCESS")
			{
				$res = $_user->Login(isset($_POST['autologin']), isset($_POST['viewonline']));
				if($res['status'] == LOGIN_SUCCESS)
				{

					$res = $tgdb_user->createUser($_POST['username'], $_POST['password'], $_user->user->data['user_email']);
					$_GET['sid'] = $_user->user->session_id;
					$_GET['logout'] = true;
					
					$session_user_id = $_user->user->data['user_id'];

					$_user->user->session_kill();
					$tgdb_user->Login(false, false);

					$db = $tgdb_user->getDatabase();
					$stmt = $db->prepare("UPDATE apiusers SET users_id = :tgdb_user_id WHERE userid = :session_user_id");

					$userData = $tgdb_user->getUserData();
					echo "UPDATE apiusers SET users_id = " . $userData['id'] . " WHERE userid = " . $session_user_id;
					exit();

					$stmt->execute([
						':tgdb_user_id' => $userData['id'],
						':session_user_id' => $session_user_id
					]);

					// Grant ADD_GAME and API_ACCESS permissions to the new user
$permStmt = $db->prepare("
    INSERT INTO users_permissions (users_id, permissions_id)
    SELECT :user_id, id FROM permissions 
    WHERE permission_text IN ('API_ACCESS', 'VALID_USER')
");
$permStmt->bindParam(':user_id', $userData['id'], PDO::PARAM_INT);
$permStmt->execute();


					header("Location: index.php");
					exit();
				}
			else
			{
				$error_msgs[] = $res['error_msg_str'];
			}
		}
		else
		{
			header("Location: index.php");
			exit();
		}
	}
	else
	{
		$error_msgs[] = "Username or Password fields can't be empty, please try again.";
	}
	}
}

require_once __DIR__ . "/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Login");
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
						<legend>Login</legend>
					</fieldset>
				</div>
				<div class="card-body">
					<form id="login_form" class="form-horizontal" method="post">
					<div class="form-group">
							<div class="input-group mb-3">
								<div class="input-group-prepend">
								<span class="input-group-text"><i class="fas fa-users" aria-hidden="true"> Username </i></span>
								</div>
								<input class="form-control" name="username" id="username" placeholder="Enter your Username" type="text">
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
							<div>
								<input type="hidden" name="redirect" value="<?= $_SERVER['HTTP_REFERER'] ?>"/>
								<div><label for="autologin"><input name="autologin" id="autologin" tabindex="4" type="checkbox"> Remember me</label></div>
								<div><label for="viewonline"><input name="viewonline" id="viewonline" tabindex="5" type="checkbox"> Hide my online status this session</label></div>
							</div>
						</div>

						<div class="form-group ">
							<button type="submit" class="btn btn-primary btn-lg btn-block">login</button>
						</div>
						<div class="login-register">
							Not got an account? Sign up <a href="register.php">here</a>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>

<?php FOOTER::print(); ?>
