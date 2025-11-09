<?php
// Include session configuration first to ensure proper session handling across subdomains
require_once __DIR__ . "/../../include/session.config.php";
require_once __DIR__ . "/../../website/include/login.common.class.php";
require_once __DIR__ . "/../../include/CommonUtils.class.php";

$error_msgs = array();
$success_msg = array();

$tgdb_user = TGDBUser::getInstance();

// Check if user is logged in and has admin permissions
if(!$tgdb_user->isLoggedIn() || !$tgdb_user->hasPermission('STAFF')) {
    header("Location: ../key.php");
    exit;
}

$db = $tgdb_user->getDatabase();

// Process form submission for approving/rejecting requests
if($_SERVER['REQUEST_METHOD'] == "POST") {
    if(isset($_POST['action']) && isset($_POST['request_id'])) {
        $action = $_POST['action'];
        $request_id = (int)$_POST['request_id'];
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        if($action === 'approve' || $action === 'reject') {
            try {
                $db->beginTransaction();
                
                // Get the request details
                $stmt = $db->prepare("SELECT * FROM api_access_requests WHERE id = :id");
                $stmt->bindParam(':id', $request_id);
                $stmt->execute();
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$request) {
                    $error_msgs[] = "Request not found.";
                } else if($request['status'] !== 'pending') {
                    $error_msgs[] = "This request has already been processed.";
                } else {
                    // Update request status
                    $status = ($action === 'approve') ? 'approved' : 'rejected';
                    $stmt = $db->prepare("UPDATE api_access_requests SET status = :status, processed_date = NOW(), processed_by = :processed_by, notes = :notes WHERE id = :id");
                    $stmt->execute([
                        ':status' => $status,
                        ':processed_by' => $tgdb_user->GetUserID(),
                        ':notes' => $notes,
                        ':id' => $request_id
                    ]);
                    
                    // If approved, grant API_ACCESS permission to the user
                    if($action === 'approve') {
                        // Get the API_ACCESS permission ID
                        $stmt = $db->prepare("SELECT id FROM permissions WHERE permission_text = 'API_ACCESS'");
                        $stmt->execute();
                        $permission = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if(!$permission) {
                            // If the permission doesn't exist, create it
                            $stmt = $db->prepare("INSERT INTO permissions (permission_text) VALUES ('API_ACCESS')");
                            $stmt->execute();
                            $permission_id = $db->lastInsertId();
                        } else {
                            $permission_id = $permission['id'];
                        }
                        
                        // Check if user already has the permission
                        $stmt = $db->prepare("SELECT COUNT(*) as has_perm FROM users_permissions WHERE users_id = :user_id AND permissions_id = :permission_id");
                        $stmt->execute([
                            ':user_id' => $request['user_id'],
                            ':permission_id' => $permission_id
                        ]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if($result['has_perm'] == 0) {
                            // Assign the API_ACCESS permission to the user
                            $stmt = $db->prepare("INSERT INTO users_permissions (users_id, permissions_id) VALUES (:user_id, :permission_id)");
                            $stmt->execute([
                                ':user_id' => $request['user_id'],
                                ':permission_id' => $permission_id
                            ]);
                        }
                    }
                    
                    // Send notification email to user
                    $to = $request['email'] ?? "user@example.com"; // You might need to fetch the user's email
                    $subject = "TheGamesDB API Access Request " . ($action === 'approve' ? "Approved" : "Rejected");
                    $message = "Hello " . $request['username'] . ",\n\n";
                    $message .= "Your request for API access has been " . ($action === 'approve' ? "approved" : "rejected") . ".\n\n";
                    
                    if($action === 'approve') {
                        $message .= "You can now access your API keys at: " . CommonUtils::$WEBSITE_BASE_URL . "API/key.php\n\n";
                    }
                    
                    if(!empty($notes)) {
                        $message .= "Additional notes: " . $notes . "\n\n";
                    }
                    
                    $message .= "Regards,\nTheGamesDB Team";
                    $headers = "From: noreply@thegamesdb.net";
                    
                    mail($to, $subject, $message, $headers);
                    
                    $db->commit();
                    $success_msg[] = "Request has been " . ($action === 'approve' ? "approved" : "rejected") . " successfully.";
                }
            } catch (PDOException $e) {
                $db->rollBack();
                $error_msgs[] = "Database error: " . $e->getMessage();
            }
        } else {
            $error_msgs[] = "Invalid action.";
        }
    }
}

// Get all requests
try {
    $stmt = $db->prepare("
        SELECT r.*, 
               u.username as processed_by_username
        FROM api_access_requests r
        LEFT JOIN users u ON r.processed_by = u.id
        ORDER BY 
            CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
            r.request_date DESC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msgs[] = "Database error: " . $e->getMessage();
    $requests = [];
}

// Include header
require_once __DIR__ . "/../../website/include/header.footer.class.php";

$Header = new HEADER();
$Header->setTitle("TGDB - Manage API Access Requests");
$Header->appendRawHeader(function() { ?>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
    <link href='//fonts.googleapis.com/css?family=Lato:300' rel='stylesheet' type='text/css'>
    <style>
        h1 {
            color: #719e40;
            letter-spacing: -3px;
            font-family: 'Lato', sans-serif;
            font-size: 60px;
            font-weight: 200;
            margin-bottom: 0;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-approved {
            background-color: #28a745;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
    </style>
<?php });?>
<?= $Header->print(); ?>

<div class="container">
    <h1 class="text-center">Manage API Access Requests</h1>
    
    <?php if(!empty($error_msgs)) : ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading">Error</h4>
        <?php foreach($error_msgs as $msg) : ?>
        <p class="mb-0"><?= htmlspecialchars($msg) ?></p>
        <?php endforeach;?>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($success_msg)) : ?>
    <div class="alert alert-success">
        <h4 class="alert-heading">Success</h4>
        <?php foreach($success_msg as $msg) : ?>
        <p class="mb-0"><?= htmlspecialchars($msg) ?></p>
        <?php endforeach;?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>API Access Requests</h3>
        </div>
        <div class="card-body">
            <?php if(empty($requests)) : ?>
                <p class="text-center">No requests found.</p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Application</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($requests as $request) : ?>
                            <tr>
                                <td><?= $request['id'] ?></td>
                                <td><?= htmlspecialchars($request['username']) ?> (ID: <?= $request['user_id'] ?>)</td>
                                <td><?= htmlspecialchars($request['app_name']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($request['request_date'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $request['status'] ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                    <?php if($request['status'] !== 'pending') : ?>
                                        <br><small>by <?= htmlspecialchars($request['processed_by_username']) ?></small>
                                        <br><small><?= date('Y-m-d H:i', strtotime($request['processed_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewModal<?= $request['id'] ?>">
                                        View
                                    </button>
                                    
                                    <?php if($request['status'] === 'pending') : ?>
                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#approveModal<?= $request['id'] ?>">
                                        Approve
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#rejectModal<?= $request['id'] ?>">
                                        Reject
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Modals for each request -->
                <?php foreach($requests as $request) : ?>
                    <!-- View Modal -->
                    <div class="modal fade" id="viewModal<?= $request['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel<?= $request['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewModalLabel<?= $request['id'] ?>">Request Details</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <dl class="row">
                                        <dt class="col-sm-4">Request ID:</dt>
                                        <dd class="col-sm-8"><?= $request['id'] ?></dd>
                                        
                                        <dt class="col-sm-4">User:</dt>
                                        <dd class="col-sm-8"><?= htmlspecialchars($request['username']) ?> (ID: <?= $request['user_id'] ?>)</dd>
                                        
                                        <dt class="col-sm-4">Application:</dt>
                                        <dd class="col-sm-8"><?= htmlspecialchars($request['app_name']) ?></dd>
                                        
                                        <dt class="col-sm-4">Description:</dt>
                                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars($request['app_description'])) ?></dd>
                                        
                                        <dt class="col-sm-4">URL:</dt>
                                        <dd class="col-sm-8">
                                            <?php if(!empty($request['app_url'])) : ?>
                                                <a href="<?= htmlspecialchars($request['app_url']) ?>" target="_blank"><?= htmlspecialchars($request['app_url']) ?></a>
                                            <?php else : ?>
                                                Not provided
                                            <?php endif; ?>
                                        </dd>
                                        
                                        <dt class="col-sm-4">Request Date:</dt>
                                        <dd class="col-sm-8"><?= date('Y-m-d H:i:s', strtotime($request['request_date'])) ?></dd>
                                        
                                        <dt class="col-sm-4">Status:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge badge-<?= $request['status'] ?>">
                                                <?= ucfirst($request['status']) ?>
                                            </span>
                                        </dd>
                                        
                                        <?php if($request['status'] !== 'pending') : ?>
                                            <dt class="col-sm-4">Processed Date:</dt>
                                            <dd class="col-sm-8"><?= date('Y-m-d H:i:s', strtotime($request['processed_date'])) ?></dd>
                                            
                                            <dt class="col-sm-4">Processed By:</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars($request['processed_by_username']) ?></dd>
                                            
                                            <?php if(!empty($request['notes'])) : ?>
                                                <dt class="col-sm-4">Notes:</dt>
                                                <dd class="col-sm-8"><?= nl2br(htmlspecialchars($request['notes'])) ?></dd>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if($request['status'] === 'pending') : ?>
                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?= $request['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel<?= $request['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="approveModalLabel<?= $request['id'] ?>">Approve Request</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="post" action="">
                                        <div class="modal-body">
                                            <p>Are you sure you want to approve this API access request from <strong><?= htmlspecialchars($request['username']) ?></strong> for <strong><?= htmlspecialchars($request['app_name']) ?></strong>?</p>
                                            <div class="form-group">
                                                <label for="notes<?= $request['id'] ?>">Notes (optional):</label>
                                                <textarea class="form-control" id="notes<?= $request['id'] ?>" name="notes" rows="3"></textarea>
                                            </div>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success">Approve</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?= $request['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel<?= $request['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="rejectModalLabel<?= $request['id'] ?>">Reject Request</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="post" action="">
                                        <div class="modal-body">
                                            <p>Are you sure you want to reject this API access request from <strong><?= htmlspecialchars($request['username']) ?></strong> for <strong><?= htmlspecialchars($request['app_name']) ?></strong>?</p>
                                            <div class="form-group">
                                                <label for="reject_notes<?= $request['id'] ?>">Reason for rejection:</label>
                                                <textarea class="form-control" id="reject_notes<?= $request['id'] ?>" name="notes" rows="3" required></textarea>
                                            </div>
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Reject</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php FOOTER::print(); ?>
