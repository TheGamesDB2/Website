<?php
// Include session configuration first to ensure proper session handling across subdomains
require_once __DIR__ . "/../include/session.config.php";
require_once __DIR__ . "/../website/include/login.common.class.php";
require_once __DIR__ . "/../include/CommonUtils.class.php";

// Configuration constants
define('DISCORD_WEBHOOK_URL', 'https://discordapp.com/api/webhooks/1437142525432303718/apIYNAGJ8rSKI2_jJM8XnmwbRQMyMP4tR1GijftO9RLZMy7MGNCNX5vK2x_L2vAm9ne7');
define('ADMIN_MANAGE_URL', 'https://thegamesdb.net/admin/manage_requests.php');

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
                
                // Get the request ID
                $request_id = $db->lastInsertId();
                
                
                // Send Discord webhook notification
                
                // Format the message for Discord
                // Sanitize inputs for Discord message
                $safe_username = htmlspecialchars($tgdb_user->GetUsername(), ENT_QUOTES, 'UTF-8');
                $safe_app_name = htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8');
                $safe_app_description = htmlspecialchars($app_description, ENT_QUOTES, 'UTF-8');
                $safe_app_url = $app_url ? htmlspecialchars($app_url, ENT_QUOTES, 'UTF-8') : "Not provided";
                
                // Truncate description if it's too long for Discord
                if (strlen($safe_app_description) > 1500) {
                    $safe_app_description = substr($safe_app_description, 0, 1497) . '...';
                }
                
                $discord_message = [
                    "content" => "🔔 **New API Access Request**\n\n👉 **Review at:** <" . ADMIN_MANAGE_URL . ">",
                    "embeds" => [
                        [
                            "title" => "API Access Request from " . $safe_username,
                            "description" => "**Application:** " . $safe_app_name . "\n\n**Description:** " . $safe_app_description,
                            "color" => 3447003, // Discord blue color
                            "fields" => [
                                [
                                    "name" => "User ID",
                                    "value" => $tgdb_user->GetUserID(),
                                    "inline" => true
                                ],
                                [
                                    "name" => "Request ID",
                                    "value" => $request_id,
                                    "inline" => true
                                ],
                                [
                                    "name" => "Application URL",
                                    "value" => $safe_app_url,
                                    "inline" => false
                                ]
                            ],
                            "footer" => [
                                "text" => "TheGamesDB API Access Request System • " . date("Y-m-d H:i:s")
                            ],
                            "timestamp" => date("c")
                        ]
                    ],
                    "components" => [
                        [
                            "type" => 1,
                            "components" => [
                                [
                                    "type" => 2,
                                    "style" => 5, // Link button style
                                    "label" => "Manage Requests",
                                    "url" => ADMIN_MANAGE_URL
                                ]
                            ]
                        ]
                    ]
                ];
                
                // Send the webhook
                try {
                    // Check if curl is available
                    if (!function_exists('curl_init')) {
                        throw new Exception('cURL is not available on this server');
                    }
                    
                    $ch = curl_init(DISCORD_WEBHOOK_URL);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($discord_message));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout to 10 seconds
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    // Log webhook response
                    if ($http_code != 204 && $http_code != 200) { // Discord returns 204 No Content on success
                        error_log("Discord webhook error: HTTP code $http_code, Response: $response");
                    }
                    
                    // Log webhook errors if any
                    if (!empty($curl_error)) {
                        error_log("Discord webhook cURL error: " . $curl_error);
                    }
                } catch (Exception $e) {
                    // Log the exception but don't stop the request process
                    error_log("Discord webhook exception: " . $e->getMessage());
                }
                
                $success_msg[] = "Your API access request has been submitted successfully. You will be notified when your request is processed.";
            }
        } catch (PDOException $e) {
            $error_msgs[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8" />
	<title>TheGamesDB API DOCs</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
	<link href='//fonts.googleapis.com/css?family=Lato:300' rel='stylesheet' type='text/css'>
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js" integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous"></script>
	<style>
		body {
			margin: 50px 0 0 0;
			padding: 0;
			width: 100%;
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			text-align: center;
			color: #aaa;
			font-size: 18px;
		}
		
		h1 {
			color: #719e40;
			letter-spacing: -3px;
			font-family: 'Lato', sans-serif;
			font-size: 100px;
			font-weight: 200;
			margin-bottom: 0;
		}
	</style>
</head>


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

<!-- Fix for CSS/JS paths in subdirectory -->
<script type="text/javascript">
// Define base URL for assets
var baseUrl = '<?= CommonUtils::$WEBSITE_BASE_URL ?>';

// Check if CSS and JS files are loading correctly
function checkResources() {
    var resources = [
        'js/jquery-3.2.1.min.js',
        'js/popper.min.1.13.0.js',
        'js/bootstrap.min.4.0.0.js',
        'css/main.css'
    ];
    
    resources.forEach(function(resource) {
        var img = new Image();
        img.onload = function() { console.log(resource + ' loaded successfully'); };
        img.onerror = function() { console.error(resource + ' failed to load'); };
        img.src = baseUrl + resource + '?check=' + new Date().getTime();
    });
}

$(document).ready(function() {
    // Check resources
    checkResources();
});
</script>

<?php FOOTER::print(); ?>
