<?php
// Include session configuration first to ensure proper session handling across subdomains
require_once __DIR__ . "/../../include/session.config.php";
require_once __DIR__ . "/../include/login.common.class.php";
require_once __DIR__ . "/../../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$tgdb_user = TGDBUser::getInstance();

// Check if user is logged in and has admin or staff permissions
if(!$tgdb_user->isLoggedIn() || (!$tgdb_user->hasPermission('STAFF') && !$tgdb_user->hasPermission('ADMIN'))) {
    header("Location: " . CommonUtils::$WEBSITE_BASE_URL);
    exit;
}

$db = $tgdb_user->getDatabase();
$is_admin = $tgdb_user->hasPermission('ADMIN');

// Get all available permissions
try {
    $stmt = $db->prepare("SELECT id, permission_text FROM permissions ORDER BY permission_text");
    $stmt->execute();
    $all_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msgs[] = "Database error: " . $e->getMessage();
    $all_permissions = [];
}

// Process search
$search_term = '';
$found_user = null;
$search_results = [];
$user_permissions = [];

// Handle user selection from search results
if(isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    
    try {
        // Get user details
        $stmt = $db->prepare("SELECT id, username, email_address FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $found_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($found_user) {
            // Get user's current permissions
            $stmt = $db->prepare("
                SELECT p.id, p.permission_text 
                FROM permissions p
                JOIN users_permissions up ON p.id = up.permissions_id
                WHERE up.users_id = ?
            ");
            $stmt->execute([$found_user['id']]);
            $user_permissions_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert to a simple array of permission IDs for easier checking
            foreach($user_permissions_result as $perm) {
                $user_permissions[$perm['id']] = $perm['permission_text'];
            }
        } else {
            $error_msgs[] = "User not found.";
        }
    } catch (PDOException $e) {
        $error_msgs[] = "Database error: " . $e->getMessage();
    }
}
// Process search
elseif(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    try {
        // Search by username or email
        $stmt = $db->prepare("SELECT id, username, email_address FROM users WHERE username LIKE ? OR email_address LIKE ? ORDER BY username LIMIT 50");
        $search_param = '%' . $search_term . '%';
        $stmt->execute([$search_param, $search_param]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(empty($search_results)) {
            $error_msgs[] = "No users found matching: " . htmlspecialchars($search_term);
        }
    } catch (PDOException $e) {
        $error_msgs[] = "Database error: " . $e->getMessage();
    }
}


// Process password reset
if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['user_id']) && isset($_POST['new_password']) && !empty($_POST['new_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    try {
        // Verify user exists
        $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$target_user) {
            $error_msgs[] = "User not found.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success_msg[] = "Password updated successfully for user: " . htmlspecialchars($target_user['username']);
            
            // Refresh found_user to show the updated user
            $stmt = $db->prepare("SELECT id, username, email_address FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $found_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get user's current permissions
            $stmt = $db->prepare("
                SELECT p.id, p.permission_text 
                FROM permissions p
                JOIN users_permissions up ON p.id = up.permissions_id
                WHERE up.users_id = ?
            ");
            $stmt->execute([$user_id]);
            $user_permissions_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $user_permissions = [];
            foreach($user_permissions_result as $perm) {
                $user_permissions[$perm['id']] = $perm['permission_text'];
            }
        }
    } catch (PDOException $e) {
        $error_msgs[] = "Database error: " . $e->getMessage();
    }
}
// Process permission updates
elseif($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['user_id']) && isset($_POST['permissions'])) {
    $user_id = (int)$_POST['user_id'];
    $new_permissions = $_POST['permissions'];
    
    // Verify user exists
    try {
        $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$target_user) {
            $error_msgs[] = "User not found.";
        } else {
            // Get current permissions
            $stmt = $db->prepare("
                SELECT p.id, p.permission_text 
                FROM permissions p
                JOIN users_permissions up ON p.id = up.permissions_id
                WHERE up.users_id = ?
            ");
            $stmt->execute([$user_id]);
            $current_permissions = [];
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $current_permissions[$row['id']] = $row['permission_text'];
            }
            
            // Start transaction
            $db->beginTransaction();
            
            // Handle special permissions
            $admin_permission_id = null;
            $staff_permission_id = null;
            
            foreach($all_permissions as $perm) {
                if($perm['permission_text'] === 'ADMIN') {
                    $admin_permission_id = $perm['id'];
                }
                if($perm['permission_text'] === 'STAFF') {
                    $staff_permission_id = $perm['id'];
                }
            }
            
            // Check for special permissions
            if($admin_permission_id && isset($new_permissions[$admin_permission_id])) {
                // Only admins can give/remove admin permission, and they can't remove their own
                if(!$is_admin || ($user_id == $tgdb_user->GetUserID())) {
                    unset($new_permissions[$admin_permission_id]);
                    $error_msgs[] = "You cannot modify ADMIN permissions.";
                }
            }
            
            if($staff_permission_id && isset($new_permissions[$staff_permission_id])) {
                // Only admins can give/remove staff permission
                if(!$is_admin) {
                    unset($new_permissions[$staff_permission_id]);
                    $error_msgs[] = "Only ADMIN users can modify STAFF permissions.";
                }
            }
            
            // Determine permissions to add and remove
            $to_add = array_diff_key($new_permissions, $current_permissions);
            $to_remove = array_diff_key($current_permissions, $new_permissions);
            
            // Remove permissions
            if(!empty($to_remove)) {
                $remove_ids = array_keys($to_remove);
                $placeholders = implode(',', array_fill(0, count($remove_ids), '?'));
                
                $stmt = $db->prepare("DELETE FROM users_permissions WHERE users_id = ? AND permissions_id IN ($placeholders)");
                $params = array_merge([$user_id], $remove_ids);
                
                for($i = 0; $i < count($params); $i++) {
                    $stmt->bindValue($i + 1, $params[$i]);
                }
                
                $stmt->execute();
            }
            
            // Add permissions
            if(!empty($to_add)) {
                $stmt = $db->prepare("INSERT INTO users_permissions (users_id, permissions_id) VALUES (?, ?)");
                
                foreach(array_keys($to_add) as $perm_id) {
                    $stmt->execute([$user_id, $perm_id]);
                }
            }
            
            // Commit transaction
            $db->commit();
            
            $success_msg[] = "Permissions updated successfully for user: " . htmlspecialchars($target_user['username']);
            
            // Refresh user permissions
            $stmt = $db->prepare("
                SELECT p.id, p.permission_text 
                FROM permissions p
                JOIN users_permissions up ON p.id = up.permissions_id
                WHERE up.users_id = ?
            ");
            $stmt->execute([$user_id]);
            $user_permissions = [];
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $user_permissions[$row['id']] = $row['permission_text'];
            }
            
            // Refresh found_user
            $found_user = $target_user;
            $found_user['id'] = $user_id;
        }
    } catch (PDOException $e) {
        $db->rollBack();
        $error_msgs[] = "Database error: " . $e->getMessage();
    }
}

// Include header
require_once __DIR__ . "/../include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Edit User Permissions");
$Header->appendRawHeader(function() { ?>
    <style>
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .permission-item {
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        
        .admin-permission {
            background-color: #ffecec;
        }
        
        .staff-permission {
            background-color: #ecf5ff;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
    </style>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Password validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const feedbackElement = document.getElementById('password_match_feedback');
            
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    if (newPasswordInput.value !== confirmPasswordInput.value) {
                        confirmPasswordInput.classList.add('is-invalid');
                        feedbackElement.style.display = 'block';
                    } else {
                        confirmPasswordInput.classList.remove('is-invalid');
                        feedbackElement.style.display = 'none';
                    }
                });
            }
        });
        
        function confirmPasswordReset() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('is-invalid');
                document.getElementById('password_match_feedback').style.display = 'block';
                return false;
            }
            
            // Check password length
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            // Confirm password reset
            return confirm('Are you sure you want to reset this user\'s password?');
        }
    </script>
<?php });?>
<?= $Header->print(); ?>

<div class="container">
    <h1 class="mt-4 mb-4">Edit User Permissions</h1>
    
    <?php if(!empty($error_msgs)) : ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading">Error</h4>
        <?php foreach($error_msgs as $msg) : ?>
        <p class="mb-0"><?= $msg ?></p>
        <?php endforeach;?>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($success_msg)) : ?>
    <div class="alert alert-success">
        <h4 class="alert-heading">Success</h4>
        <?php foreach($success_msg as $msg) : ?>
        <p class="mb-0"><?= $msg ?></p>
        <?php endforeach;?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>Search User</h3>
        </div>
        <div class="card-body">
            <form method="get" action="" class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by username or email" value="<?= htmlspecialchars($search_term) ?>" required>
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            
            <?php if(!empty($search_results)) : ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Search Results</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($search_results as $user) : ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email_address']) ?></td>
                                    <td>
                                        <a href="?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Select</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($found_user) : ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5>User Details</h5>
                </div>
                <div class="card-body">
                    <div class="user-info mb-4">
                        <h4>User: <?= htmlspecialchars($found_user['username']) ?></h4>
                        <p>Email: <?= htmlspecialchars($found_user['email_address']) ?></p>
                        <p>ID: <?= $found_user['id'] ?></p>
                    </div>
                    
                    <ul class="nav nav-tabs" id="userTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="permissions-tab" data-toggle="tab" href="#permissions" role="tab">Permissions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="password-tab" data-toggle="tab" href="#password" role="tab">Reset Password</a>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="userTabsContent">
                        <!-- Permissions Tab -->
                        <div class="tab-pane fade show active" id="permissions" role="tabpanel">
                            <form method="post" action="">
                                <input type="hidden" name="user_id" value="<?= $found_user['id'] ?>">
                                
                                <h5>Manage Permissions</h5>
                                <div class="permission-grid">
                                    <?php foreach($all_permissions as $permission) : ?>
                                        <?php 
                                            $is_checked = isset($user_permissions[$permission['id']]);
                                            $is_admin_perm = $permission['permission_text'] === 'ADMIN';
                                            $is_staff_perm = $permission['permission_text'] === 'STAFF';
                                            $is_disabled = ($is_admin_perm && (!$is_admin || $found_user['id'] == $tgdb_user->GetUserID())) || 
                                                          ($is_staff_perm && !$is_admin);
                                            $perm_class = $is_admin_perm ? 'admin-permission' : ($is_staff_perm ? 'staff-permission' : '');
                                        ?>
                                        <div class="permission-item <?= $perm_class ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[<?= $permission['id'] ?>]" id="perm_<?= $permission['id'] ?>" 
                                                       <?= $is_checked ? 'checked' : '' ?> <?= $is_disabled ? 'disabled' : '' ?>>
                                                <label class="form-check-label" for="perm_<?= $permission['id'] ?>">
                                                    <?= htmlspecialchars($permission['permission_text']) ?>
                                                    <?php if($is_admin_perm) : ?>
                                                        <span class="badge badge-danger">ADMIN ONLY</span>
                                                    <?php elseif($is_staff_perm) : ?>
                                                        <span class="badge badge-primary">ADMIN ONLY</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-success">Update Permissions</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Password Reset Tab -->
                        <div class="tab-pane fade" id="password" role="tabpanel">
                            <form method="post" action="" onsubmit="return confirmPasswordReset();">
                                <input type="hidden" name="user_id" value="<?= $found_user['id'] ?>">
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" required>
                                    <div id="password_match_feedback" class="invalid-feedback">Passwords do not match.</div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-danger">Reset Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php FOOTER::print(); ?>
