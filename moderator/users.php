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

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['suspend_user'])) {
        $target_user_id = $_POST['user_id'];
        $reason = $_POST['reason'];
        $duration_days = $_POST['duration_days'];
        
        $suspended_until = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
        
        $query = "UPDATE users SET is_suspended = TRUE, suspended_until = :until, suspension_reason = :reason WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':until', $suspended_until);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':id', $target_user_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id, reason) 
                          VALUES (:mod_id, 'user_suspend', 'user', :target_id, :reason)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $target_user_id);
            $log_stmt->bindParam(':reason', $reason);
            $log_stmt->execute();
            
            $success = 'User suspended successfully!';
        }
    }
    
    if(isset($_POST['unsuspend_user'])) {
        $target_user_id = $_POST['user_id'];
        
        $query = "UPDATE users SET is_suspended = FALSE, suspended_until = NULL, suspension_reason = NULL WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $target_user_id);
        
        if($stmt->execute()) {
            // Log action
            $log_query = "INSERT INTO moderator_logs (moderator_id, action_type, target_type, target_id) 
                          VALUES (:mod_id, 'user_unsuspend', 'user', :target_id)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':mod_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':target_id', $target_user_id);
            $log_stmt->execute();
            
            $success = 'User unsuspended!';
        }
    }
}

// Get users
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$where_conditions[] = "u.is_admin = FALSE"; // Moderators cannot manage admins

if($filter == 'suspended') {
    $where_conditions[] = "u.is_suspended = TRUE";
} elseif($filter == 'reported') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM reports WHERE reported_type = 'user' AND reported_id = u.id AND status = 'pending')";
}

if($search) {
    $where_conditions[] = "(u.username LIKE :search OR u.email LIKE :search)";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM listings WHERE user_id = u.id) as listing_count,
          (SELECT COUNT(*) FROM reports WHERE reported_type = 'user' AND reported_id = u.id AND status = 'pending') as report_count
          FROM users u
          $where_clause
          ORDER BY u.created_at DESC
          LIMIT 50";

$stmt = $db->prepare($query);
if($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$users = $stmt->fetchAll();

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<div class="mod-container">
    <div class="mod-header">
        <h1>👥 User Management</h1>
        <p style="color: var(--text-gray);">Manage users and handle violations</p>
    </div>

    <div class="mod-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php" class="active">👥 Users</a>
        <a href="listings.php">📝 Listings</a>
        <a href="reports.php">🚨 Reports</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="mod-section">
        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="?filter=all" class="btn-secondary btn-small <?php echo $filter == 'all' ? 'active' : ''; ?>">
                All Users
            </a>
            <a href="?filter=suspended" class="btn-secondary btn-small <?php echo $filter == 'suspended' ? 'active' : ''; ?>">
                Suspended
            </a>
            <a href="?filter=reported" class="btn-secondary btn-small <?php echo $filter == 'reported' ? 'active' : ''; ?>">
                Reported
            </a>
        </div>
        
        <form method="GET" style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="search" placeholder="Search username or email..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
            <button type="submit" class="btn-primary">Search</button>
            <?php if($search): ?>
            <a href="users.php?filter=<?php echo $filter; ?>" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Listings</th>
                        <th>Reports</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user_item): ?>
                    <tr>
                        <td><?php echo $user_item['id']; ?></td>
                        <td>
                            <a href="../profile.php?id=<?php echo $user_item['id']; ?>" style="color: var(--primary-blue);" target="_blank">
                                <?php echo htmlspecialchars($user_item['username']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                        <td>
                            <?php if($user_item['is_suspended']): ?>
                            <span style="color: var(--danger-red);">🚫 Suspended</span>
                            <?php else: ?>
                            <span style="color: var(--success-green);">✓ Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user_item['listing_count']; ?></td>
                        <td>
                            <?php if($user_item['report_count'] > 0): ?>
                            <span style="color: var(--danger-red); font-weight: bold;">
                                <?php echo $user_item['report_count']; ?>
                            </span>
                            <?php else: ?>
                            0
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user_item['created_at'])); ?></td>
                        <td>
                            <?php if($user_item['is_suspended']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="unsuspend_user" class="action-btn edit">
                                    Unsuspend
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="action-btn delete" onclick="showSuspendModal(<?php echo $user_item['id']; ?>, '<?php echo htmlspecialchars($user_item['username']); ?>')">
                                Suspend
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div id="suspendModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Suspend User: <span id="suspendUsername"></span></h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="suspendUserId">
            <div class="form-group">
                <label>Suspension Duration</label>
                <select name="duration_days" required>
                    <option value="1">1 Day</option>
                    <option value="3">3 Days</option>
                    <option value="7" selected>7 Days</option>
                    <option value="30">30 Days</option>
                    <option value="365">1 Year</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <textarea name="reason" rows="4" required placeholder="Explain why this user is being suspended..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeSuspendModal()">Cancel</button>
                <button type="submit" name="suspend_user" class="btn-danger btn-block">Suspend User</button>
            </div>
        </form>
    </div>
</div>

<script>
function showSuspendModal(userId, username) {
    document.getElementById('suspendUserId').value = userId;
    document.getElementById('suspendUsername').textContent = username;
    document.getElementById('suspendModal').style.display = 'flex';
}

function closeSuspendModal() {
    document.getElementById('suspendModal').style.display = 'none';
}

document.getElementById('suspendModal')?.addEventListener('click', function(e) {
    if(e.target === this) {
        closeSuspendModal();
    }
});
</script>

<?php include '../views/footer.php'; ?>