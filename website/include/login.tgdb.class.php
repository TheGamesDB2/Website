<?php

include_once __DIR__ . "/../../include/db.config.php";	

class TGDBUser
{
	private $dbh;
	private $user_data = null;

	private function __construct()
	{
		$db = database::getInstance();
		$this->dbh = $db->dbh;
	}

	public static function getInstance()
	{
		static $instance = null;
		if (!isset($instance))
		{
			$object = __CLASS__;
			$instance = new $object;
		}
		return $instance;
	}

	/**
	 * Login a user with the provided credentials
	 * 
	 * @param bool $login_autologin Whether to set autologin
	 * @param bool $login_viewonline Whether to show user as online
	 * @return array Login status and any error messages
	 */
	function Login($login_autologin, $login_viewonline)
	{
		$ret = array(
			'status' => 'LOGIN_ERROR',
			'error_msg' => '',
			'error_msg_str' => '',
			'user_row' => null
		);
		
		// Get login credentials from POST
		$login_username = isset($_POST['username']) ? trim($_POST['username']) : '';
		$login_password = isset($_POST['password']) ? $_POST['password'] : '';

		if (empty($login_username) || empty($login_password)) {
			echo "working on the site, please bear with us.";
			exit();
			$ret['error_msg_str'] = 'Please provide both username and password.';
			return $ret;
		}

		try {
			// Check if user exists and get their data
			$stmt = $this->dbh->prepare("SELECT id, username, password FROM users WHERE username = :username and hashed = ''");
			$stmt->bindParam(':username', $login_username);
			$stmt->execute();
			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user) {
				$ret['error_msg_str'] = 'Invalid username or password.';
				return $ret;
			}

			

			// Verify password (assuming password is hashed with password_hash())
			if (!password_verify($login_password, $user['password'])) {
				$ret['error_msg_str'] = 'Invalid username or password.';
				return $ret;
			}

			// Login successful
			$ret['status'] = 'LOGIN_SUCCESS';
			$ret['user_row'] = $user;

			// Set session data
			$this->user_data = $user;
			$_SESSION['user_id'] = $user['id'];
			$_SESSION['username'] = $user['username'];
			$_SESSION['is_logged_in'] = true;
			
			// Handle autologin if requested
			if ($login_autologin) {
				$token = bin2hex(random_bytes(32));
				$expiry = time() + (86400 * 30); // 30 days
				
				// Delete any existing tokens for this user
				$deleteStmt = $this->dbh->prepare("DELETE FROM user_tokens WHERE user_id = :user_id");
				$deleteStmt->execute([':user_id' => $user['id']]);
				
				// Store token in database
				$stmt = $this->dbh->prepare("INSERT INTO user_tokens (user_id, token, expires) VALUES (:user_id, :token, :expires)");
				$stmt->execute([
					':user_id' => $user['id'], 
					':token' => $token, 
					':expires' => $expiry
				]);
				
				// Set cookie
				setcookie('tgdb_autologin', $user['id'] . ':' . $token, $expiry, '/', '', false, true);
			}
		} catch (PDOException $e) {
			$ret['error_msg_str'] = 'Database error: ' . $e->getMessage();
		}

		return $ret;
	}

	/**
	 * Check if user is currently logged in
	 * 
	 * @return bool True if user is logged in
	 */
	function isLoggedIn()
	{
		return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
	}
	
	/**
	 * Check for autologin cookie and authenticate user if valid
	 * 
	 * @return bool True if user was auto-logged in
	 */
	function checkAutologin()
	{
		// If user is already logged in, no need to check autologin
		if ($this->isLoggedIn()) {
			return true;
		}
		
		// Check if autologin cookie exists
		if (!isset($_COOKIE['tgdb_autologin'])) {
			return false;
		}
		
		// Parse the cookie value
		$cookie_data = explode(':', $_COOKIE['tgdb_autologin']);
		if (count($cookie_data) !== 2) {
			// Invalid cookie format, delete it
			setcookie('tgdb_autologin', '', time() - 3600, '/', '', false, true);
			return false;
		}
		
		list($user_id, $token) = $cookie_data;
		
		try {
			// Check if token exists and is valid
			$stmt = $this->dbh->prepare("
				SELECT u.id, u.username, t.expires 
				FROM user_tokens t
				JOIN users u ON t.user_id = u.id
				WHERE t.user_id = :user_id 
				AND t.token = :token 
				AND t.expires > :now
			");
			
			$now = time();
			$stmt->execute([
				':user_id' => $user_id,
				':token' => $token,
				':now' => $now
			]);
			
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$result) {
				// Token not found or expired, delete cookie
				setcookie('tgdb_autologin', '', time() - 3600, '/', '', false, true);
				return false;
			}
			
			// Auto-login successful, set session data
			$this->user_data = $result;
			$_SESSION['user_id'] = $result['id'];
			$_SESSION['username'] = $result['username'];
			$_SESSION['is_logged_in'] = true;
			
			// Extend the token expiration if it's going to expire soon (within 7 days)
			if ($result['expires'] - $now < 86400 * 7) {
				$new_expiry = time() + (86400 * 30); // 30 days
				
				// Update token expiration
				$updateStmt = $this->dbh->prepare("UPDATE user_tokens SET expires = :expires WHERE user_id = :user_id AND token = :token");
				$updateStmt->execute([
					':expires' => $new_expiry,
					':user_id' => $user_id,
					':token' => $token
				]);
				
				// Update cookie expiration
				setcookie('tgdb_autologin', $user_id . ':' . $token, $new_expiry, '/', '', false, true);
			}
			
			return true;
			
		} catch (PDOException $e) {
			// Log error or handle it as needed
			return false;
		}
	}

	/**
	 * Log out the current user
	 * 
	 * @return bool True if logout was successful
	 */
	function Logout()
	{
		// Check if user is logged in and session ID matches
		$sid = isset($_GET['sid']) ? $_GET['sid'] : '';
		
		if ($this->isLoggedIn() && session_id() === $sid) {
			// Get user ID before clearing session
			$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
			
			// Clear session data
			$_SESSION = array();
			
			// Delete the session cookie
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000,
					$params["path"], $params["domain"],
					$params["secure"], $params["httponly"]
				);
			}
			
			// Delete autologin cookie if it exists
			setcookie('tgdb_autologin', '', time() - 3600, '/', '', false, true);
			
			// Delete autologin tokens from database
			if ($user_id) {
				try {
					$stmt = $this->dbh->prepare("DELETE FROM user_tokens WHERE user_id = :user_id");
					$stmt->execute([':user_id' => $user_id]);
				} catch (PDOException $e) {
					// Silently fail, not critical
				}
			}
			
			// Destroy the session
			session_destroy();
			
			// Clear user data
			$this->user_data = null;
			
			return true;
		}
		
		return false;
	}

	/**
	 * Check if user has a specific permission
	 * 
	 * @param string $perm Permission text to check
	 * @return bool True if user has the permission
	 */
	function hasPermission($perm)
	{
		if (!$this->isLoggedIn()) {
			return false;
		}

		try {
			$stmt = $this->dbh->prepare("
				SELECT COUNT(*) as has_perm 
				FROM users_permissions up
				JOIN permissions p ON up.permissions_id = p.id
				WHERE up.users_id = :user_id AND p.permission_text = :permission_text
			");
			$stmt->execute([
				':user_id' => $_SESSION['user_id'],
				':permission_text' => $perm
			]);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			
			return ($result['has_perm'] > 0);
		} catch (PDOException $e) {
			// Log error or handle it as needed
			return false;
		}
	}

	/**
	 * Get the current user's username
	 * 
	 * @return string Username or empty string if not logged in
	 */
	function GetUsername()
	{
		return $this->isLoggedIn() ? $_SESSION['username'] : '';
	}

	/**
	 * Get the current user's ID
	 * 
	 * @return int User ID or 0 if not logged in
	 */
	function GetUserID()
	{
		return $this->isLoggedIn() ? $_SESSION['user_id'] : 0;
	}

	/**
	 * Get the current user's session ID
	 * 
	 * @return string Session ID
	 */
	function GetUserSessionID()
	{
		return session_id();
	}
	
	/**
	 * Get the database connection
	 * 
	 * @return PDO The database connection
	 */
	function getDatabase()
	{
		return $this->dbh;
	}
	
	/**
	 * Get the current user data
	 * 
	 * @return array|null The user data or null if not logged in
	 */
	function getUserData()
	{
		return $this->user_data;
	}

	/**
	 * Create a new user with ADD_GAME permission
	 * 
	 * @param string $username Username for the new user
	 * @param string $password Password for the new user (will be hashed)
	 * @param string $email Email address for the new user
	 * @return array Result of the operation with status and message
	 */
	function createUser($username, $password, $email)
	{
		$result = [
			'success' => false,
			'message' => '',
			'user_id' => null
		];

		// Validate inputs
		if (empty($username) || empty($password) || empty($email)) {
			$result['message'] = 'Username, password, and email are required.'; 
			return $result;
		}

		// Validate email format
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$result['message'] = 'Invalid email format.';
			return $result;
		}

		try {
			$this->dbh->beginTransaction();

			$username = trim($username);
			$password = trim($password);
			$email = trim($email);

			// Check if username already exists
			$stmt = $this->dbh->prepare("SELECT id FROM users WHERE username = :username");
			$stmt->bindParam(':username', $username);
			$stmt->execute();
			
			if ($stmt->fetch(PDO::FETCH_ASSOC)) {
				$result['message'] = 'Username already exists.';
				return $result;
			}

			// Hash the password
			$hashed_password = password_hash($password, PASSWORD_DEFAULT);

			// Insert the new user
			$stmt = $this->dbh->prepare("INSERT INTO users (username, password, email_address) VALUES (:username, :password, :email)");
			$stmt->bindParam(':username', $username);
			$stmt->bindParam(':password', $hashed_password);
			$stmt->bindParam(':email', $email);
			$stmt->execute();

			$user_id = $this->dbh->lastInsertId();
			
			// Get the ADD_GAME permission ID
			$stmt = $this->dbh->prepare("SELECT id FROM permissions WHERE permission_text = 'ADD_GAME'");
			$stmt->execute();
			$permission = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$permission) {
				// If the permission doesn't exist, create it
				$stmt = $this->dbh->prepare("INSERT INTO permissions (permission_text) VALUES ('ADD_GAME')");
				$stmt->execute();
				$permission_id = $this->dbh->lastInsertId();
			} else {
				$permission_id = $permission['id'];
			}
			
			// Assign the ADD_GAME permission to the user
			$stmt = $this->dbh->prepare("INSERT INTO users_permissions (users_id, permissions_id) VALUES (:user_id, :permission_id)");
			$stmt->bindParam(':user_id', $user_id);
			$stmt->bindParam(':permission_id', $permission_id);
			$stmt->execute();
			
			$this->dbh->commit();
			
			$result['success'] = true;
			$result['message'] = 'User created successfully with ADD_GAME permission.';
			$result['user_id'] = $user_id;
			
		} catch (PDOException $e) {
			$this->dbh->rollBack();
			$result['message'] = 'Database error: ' . $e->getMessage();
		}
		
		return $result;
	}

	/**
	 * Request a password reset for a user with the given email
	 * 
	 * @param string $email Email address of the user
	 * @return array Result of the operation with status and message
	 */
	function requestPasswordReset($email)
	{
		$result = [
			'success' => false,
			'message' => '',
			'token' => null
		];

		// Validate email
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$result['message'] = 'Invalid email format.';
			return $result;
		}

		try {
			// Check if email exists in the database
			$stmt = $this->dbh->prepare("SELECT id, username FROM users WHERE email_address = :email");
			$stmt->bindParam(':email', $email);
			$stmt->execute();
			$user = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$user) {
				// Don't reveal if email exists or not for security reasons
				$result['success'] = true;
				$result['message'] = 'If your email address exists in our database, you will receive a password reset link.';
				return $result;
			}

			// Generate a unique token
			$token = bin2hex(random_bytes(32));
			$expires = time() + 3600; // Token expires in 1 hour
			
			// Store token in database
			$stmt = $this->dbh->prepare("INSERT INTO password_reset_tokens (user_id, token, expires, used) VALUES (:user_id, :token, :expires, 0)");
			$stmt->execute([
				':user_id' => $user['id'],
				':token' => $token,
				':expires' => $expires
			]);
			
			$result['success'] = true;
			$result['message'] = 'Password reset token generated successfully.';
			$result['token'] = $token;
			$result['user'] = $user;
			
		} catch (PDOException $e) {
			$result['message'] = 'Database error: ' . $e->getMessage();
		}
		
		return $result;
	}

	/**
	 * Reset a user's password using a valid token
	 * 
	 * @param string $token The reset token
	 * @param string $new_password The new password
	 * @return array Result of the operation with status and message
	 */
	function resetPassword($token, $new_password)
	{
		$result = [
			'success' => false,
			'message' => ''
		];

		// Validate inputs
		if (empty($token) || empty($new_password)) {
			$result['message'] = 'Token and new password are required.';
			return $result;
		}

		if (strlen($new_password) < 8) {
			$result['message'] = 'Password must be at least 8 characters long.';
			return $result;
		}

		try {
			$this->dbh->beginTransaction();

			// Check if token is valid and not expired
			$stmt = $this->dbh->prepare("SELECT user_id, expires FROM password_reset_tokens WHERE token = :token AND used = 0");
			$stmt->bindParam(':token', $token);
			$stmt->execute();
			$token_data = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$token_data || $token_data['expires'] <= time()) {
				$result['message'] = 'Invalid or expired password reset token.';
				return $result;
			}

			// Hash the new password
			$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

			// Update user's password
			$stmt = $this->dbh->prepare("UPDATE users SET password = :password WHERE id = :user_id");
			$stmt->bindParam(':password', $hashed_password);
			$stmt->bindParam(':user_id', $token_data['user_id']);
			$stmt->execute();

			// Mark token as used
			$stmt = $this->dbh->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = :token");
			$stmt->bindParam(':token', $token);
			$stmt->execute();

			$this->dbh->commit();
			
			$result['success'] = true;
			$result['message'] = 'Password reset successfully.';
			
		} catch (PDOException $e) {
			$this->dbh->rollBack();
			$result['message'] = 'Database error: ' . $e->getMessage();
		}
		
		return $result;
	}
}

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
