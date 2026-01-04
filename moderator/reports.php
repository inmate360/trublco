<?php
session_start();
require_once '../config/database.php';

// Check if user is moderator
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check moderator status
$query = "SELECT is_moderator, is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

if(!$user || (!$user['is_moderator'] && !$user['is_admin'])) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Ensure reports table exists
try {
    $create_table = "CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_type ENUM('listing', 'user', 'message') NOT NULL,
        reported_id INT NOT NULL,
        reason VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
        action_taken TEXT,
        resolved_by INT NULL,
        resolved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_reporter (reporter_id),
        INDEX idx_reported (reported_type, reported_id)
    )";
    $db->exec($create_table);
} catch(PDOException $e) {
    // Table might already exist
}

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['resolve_report'])) {
        $report_id = $_POST['report_id'];
        $action_taken = $_POST['action_taken'];
        
        $query = "UPDATE reports 
                  SET status = 'resolved', action_taken = :action, resolved_by = :mod_id, resolved_at = NOW()
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':action', $action_taken);
        $stmt->bindParam(':mod_id', $_SESSION['user_id']);
        $stmt->bindParam(':id', $report_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id, reason) 
                          VALUES (:mod_id, 'report_resolve', 'report', :target_id, :action)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $report_id);
            $log_stmt->bindParam(':action', $action_taken);
            $log_stmt->execute();
            
            $success = 'Report resolved successfully!';
        }
    }
    
    if(isset($_POST['dismiss_report'])) {
        $report_id = $_POST['report_id'];
        
        $query = "UPDATE reports 
                  SET status = 'dismissed', resolved_by = :mod_id, resolved_at = NOW()
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mod_id', $_SESSION['user_id']);
        $stmt->bindParam(':id', $report_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id) 
                          VALUES (:mod_id, 'report_dismiss', 'report', :target_id)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $report_id);
            $log_stmt->execute();
            
            $success = 'Report dismissed!';
        }
    }
}

// Get reports
$status_filter = $_GET['status'] ?? 'pending';

try {
    $query = "SELECT r.*, u.username as reporter_name
              FROM reports r
              LEFT JOIN users u ON r.reporter_id = u.id
              WHERE r.status = :status
              ORDER BY r.created_at DESC
              LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status_filter);
    $stmt->execute();
    $reports = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching reports: " . $e->getMessage());
    $reports = [];
}

// Get status counts
$counts = [];
foreach(['pending', 'resolved', 'dismissed'] as $status) {
    try {
        $query = "SELECT COUNT(*) as count FROM reports WHERE status = :status";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        $counts[$status] = $stmt->fetch()['count'];
    } catch(PDOException $e) {
        $counts[$status] = 0;
    }
}

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<div class="mod-container">
    <div class="mod-header">
        <h1>🚨 Reports Management</h1>
        <p style="color: var(--text-gray);">Review and handle user reports</p>
    </div>

    <div class="mod-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php">👥 Users</a>
        <a href="listings.php">📝 Listings</a>
        <a href="reports.php" class="active">🚨 Reports</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="mod-section">
        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="?status=pending" class="btn-secondary btn-small <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending (<?php echo $counts['pending']; ?>)
            </a>
            <a href="?status=resolved" class="btn-secondary btn-small <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">
                Resolved (<?php echo $counts['resolved']; ?>)
            </a>
            <a href="?status=dismissed" class="btn-secondary btn-small <?php echo $status_filter == 'dismissed' ? 'active' : ''; ?>">
                Dismissed (<?php echo $counts['dismissed']; ?>)
            </a>
        </div>
        
        <?php if(count($reports) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Reported ID</th>
                        <th>Reason</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reports as $report): ?>
                    <tr>
                        <td><?php echo $report['id']; ?></td>
                        <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                        <td>
                            <span class="category-badge" style="font-size: 0.75rem;">
                                <?php echo ucfirst($report['reported_type']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if($report['reported_type'] == 'listing'): ?>
                            <a href="../listing.php?id=<?php echo $report['reported_id']; ?>" style="color: var(--primary-blue);" target="_blank">
                                View Listing
                            </a>
                            <?php elseif($report['reported_type'] == 'user'): ?>
                            <a href="../profile.php?id=<?php echo $report['reported_id']; ?>" style="color: var(--primary-blue);" target="_blank">
                                View User
                            </a>
                            <?php else: ?>
                            ID: <?php echo $report['reported_id']; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: var(--danger-red);">
                                <?php echo htmlspecialchars($report['reason']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($report['description']); ?>
                            </div>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                        <td>
                            <?php if($status_filter == 'pending'): ?>
                            <button class="action-btn edit" onclick="showResolveModal(<?php echo $report['id']; ?>)">
                                Resolve
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <button type="submit" name="dismiss_report" class="action-btn delete" onclick="return confirm('Dismiss this report?');">
                                    Dismiss
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color: var(--text-gray);">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: rgba(66, 103, 245, 0.05); border-radius: 10px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
            <h3>No Reports Found</h3>
            <p style="color: var(--text-gray);">No reports match your current filter</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resolve Modal -->
<div id="resolveModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Resolve Report</h3>
        <form method="POST">
            <input type="hidden" name="report_id" id="resolveReportId">
            <div class="form-group">
                <label>Action Taken</label>
                <select name="action_taken" required>
                    <option value="">Select action...</option>
                    <option value="Content removed">Content removed</option>
                    <option value="User warned">User warned</option>
                    <option value="User suspended">User suspended</option>
                    <option value="No action needed">No action needed</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeResolveModal()">Cancel</button>
                <button type="submit" name="resolve_report" class="btn-success btn-block">Resolve</button>
            </div>
        </form>
    </div>
</div>

<script>
function showResolveModal(reportId) {
    document.getElementById('resolveReportId').value = reportId;
    document.getElementById('resolveModal').style.display = 'flex';
}

function closeResolveModal() {
    document.getElementById('resolveModal').style.display = 'none';
}

document.getElementById('resolveModal')?.addEventListener('click', function(e) {
    if(e.target === this) closeResolveModal();
});
</script>

<?php include '../views/footer.php'; ?>