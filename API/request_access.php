<?php
// Include session configuration first to ensure proper session handling across subdomains
require_once __DIR__ . "/../include/session.config.php";
require_once __DIR__ . "/../website/include/login.common.class.php";
require_once __DIR__ . "/../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$tgdb_user = TGDBUser::getInstance();

// Check if user is logged in
if(!$tgdb_user->isLoggedIn()) {
    header("Location: key.php");
    exit;
}

// Check if user already has API access
if($tgdb_user->hasPermission('API_ACCESS')) {
    header("Location: key.php");
    exit;
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] == "POST") {
    // Validate form data
    $app_name = isset($_POST['app_name']) ? trim($_POST['app_name']) : '';
    $app_description = isset($_POST['app_description']) ? trim($_POST['app_description']) : '';
    $app_url = isset($_POST['app_url']) ? trim($_POST['app_url']) : '';
    
    if(empty($app_name)) {
        $error_msgs[] = "Application name is required.";
    }
    
    if(empty($app_description)) {
        $error_msgs[] = "Application description is required.";
    }
    
    // Validate URL if provided
    if(!empty($app_url) && !filter_var($app_url, FILTER_VALIDATE_URL)) {
        $error_msgs[] = "Please enter a valid URL.";
    }
    
    // If no errors, process the request
    if(empty($error_msgs)) {
        try {
            $db = $tgdb_user->getDatabase();
            
            // Check if there's already a pending request
            $stmt = $db->prepare("SELECT id FROM api_access_requests WHERE user_id = :user_id AND status = 'pending'");
            $stmt->bindParam(':user_id', $tgdb_user->GetUserID());
            $stmt->execute();
            
            if($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error_msgs[] = "You already have a pending API access request. Please wait for it to be processed.";
            } else {
                // Create a new request
                $stmt = $db->prepare("INSERT INTO api_access_requests (user_id, username, app_name, app_description, app_url, request_date, status) 
                                     VALUES (:user_id, :username, :app_name, :app_description, :app_url, NOW(), 'pending')");
                $stmt->execute([
                    ':user_id' => $tgdb_user->GetUserID(),
                    ':username' => $tgdb_user->GetUsername(),
                    ':app_name' => $app_name,
                    ':app_description' => $app_description,
                    ':app_url' => $app_url
                ]);
                
                // Send notification email to admin (optional)
                $admin_email = "admin@thegamesdb.net"; // Replace with actual admin email
                $subject = "New API Access Request";
                $message = "A new API access request has been submitted:\n\n";
                $message .= "User: " . $tgdb_user->GetUsername() . " (ID: " . $tgdb_user->GetUserID() . ")\n";
                $message .= "Application: " . $app_name . "\n";
                $message .= "Description: " . $app_description . "\n";
                $message .= "URL: " . ($app_url ? $app_url : "Not provided") . "\n\n";
                $message .= "To review this request, please login to the admin panel.";
                $headers = "From: noreply@thegamesdb.net";
                
                mail($admin_email, $subject, $message, $headers);
                
                $success_msg[] = "Your API access request has been submitted successfully. You will be notified when your request is processed.";
            }
        } catch (PDOException $e) {
            $error_msgs[] = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
require_once __DIR__ . "/../website/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - API Access Request");
$Header->appendRawHeader(function() { ?>
    <link href='//fonts.googleapis.com/css?family=Lato:300' rel='stylesheet' type='text/css'>
    <style>
        h1 {
            color: #719e40;
            letter-spacing: -3px;
            font-family: 'Lato', sans-serif;
            font-size: 100px;
            font-weight: 200;
            margin-bottom: 0;
        }
    </style>
<?php });?>
<?= $Header->print(); ?>

<div class="container">
    <h1 class="text-center">API Access Request</h1>
    
    <?php if(!empty($error_msgs)) : ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-danger">
                <h4 class="alert-heading">Error</h4>
                <?php foreach($error_msgs as $msg) : ?>
                <p class="mb-0"><?= htmlspecialchars($msg) ?></p>
                <?php endforeach;?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($success_msg)) : ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-success">
                <h4 class="alert-heading">Success</h4>
                <?php foreach($success_msg as $msg) : ?>
                <p class="mb-0"><?= htmlspecialchars($msg) ?></p>
                <?php endforeach;?>
            </div>
            <div class="text-center mt-4">
                <a href="key.php" class="btn btn-primary">Return to API Keys</a>
            </div>
        </div>
    </div>
    <?php else : ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Request API Access</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="request_access.php">
                        <div class="form-group">
                            <label for="app_name">Application Name</label>
                            <input type="text" class="form-control" id="app_name" name="app_name" value="<?= isset($app_name) ? htmlspecialchars($app_name) : '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="app_description">How will you use our API?</label>
                            <textarea class="form-control" id="app_description" name="app_description" rows="3" required><?= isset($app_description) ? htmlspecialchars($app_description) : '' ?></textarea>
                            <small class="form-text text-muted">Please provide details about your application and how you plan to use the API.</small>
                        </div>
                        <div class="form-group">
                            <label for="app_url">Application URL (if applicable)</label>
                            <input type="url" class="form-control" id="app_url" name="app_url" value="<?= isset($app_url) ? htmlspecialchars($app_url) : '' ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                            <a href="key.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php FOOTER::print(); ?>
