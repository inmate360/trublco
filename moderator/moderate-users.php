<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Moderator.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$moderator = new Moderator($db);

// Check if user is moderator
if(!$moderator->isModerator($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Handle user actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['suspend_user'])) {
        $user_id = $_POST['user_id'];
        $reason = $_POST['suspension_reason'] ?? 'Violation of terms of service';
        $duration_days = $_POST['duration_days'] ?? 7;
        
        if($moderator->suspendUser($user_id, $_SESSION['user_id'], $reason, $duration_days)) {
            $success = 'User suspended successfully!';
        } else {
            $error = 'Failed to suspend user';
        }
    }
    
    if(isset($_POST['unsuspend_user'])) {
        $user_id = $_POST['user_id'];
        
        if($moderator->unsuspendUser($user_id, $_SESSION['user_id'])) {
            $success = 'User unsuspended!';
        } else {
            $error = 'Failed to unsuspend user';
        }
    }
    
    if(isset($_POST['ban_user'])) {
        $user_id = $_POST['user_id'];
        $reason = $_POST['ban_reason'] ?? 'Permanent ban';
        
        if($moderator->banUser($user_id, $_SESSION['user_id'], $reason)) {
            $success = 'User banned permanently!';
        } else {
            $error = 'Failed to ban user';
        }
    }
    
    if(isset($_POST['warn_user'])) {
        $user_id = $_POST['user_id'];
        $warning_message = $_POST['warning_message'];
        
        if($moderator->warnUser($user_id, $_SESSION['user_id'], $warning_message)) {
            $success = 'Warning sent to user!';
        } else {
            $error = 'Failed to send warning';
        }
    }
}

// Get flagged users
$flagged_users = $moderator->getFlaggedUsers();

// Get suspended users
$suspended_users = $moderator->getSuspendedUsers();

// Get recent user actions
$recent_actions = $moderator->getRecentUserActions(20);

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.moderator-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.moderator-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.moderator-nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.moderator-nav a {
    padding: 0.75rem 1.5rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-white);
    transition: all 0.3s;
}

.moderator-nav a:hover, .moderator-nav a.active {
    background: rgba(66, 103, 245, 0.1);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.moderator-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.moderator-section h2 {
    margin-bottom: 1.5rem;
    color: var(--primary-blue);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: rgba(66, 103, 245, 0.1);
    padding: 1rem;
    text-align: left;
    color: var(--text-white);
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-gray);
}

.data-table tr:hover {
    background: rgba(66, 103, 245, 0.05);
}

.action-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-block;
    margin-right: 0.5rem;
    border: none;
    cursor: pointer;
}

.action-btn.view {
    background: rgba(6, 182, 212, 0.2);
    color: var(--info-cyan);
}

.action-btn.warn {
    background: rgba(245, 158, 11, 0.2);
    color: var(--warning-orange);
}

.action-btn.suspend {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

.action-btn.ban {
    background: rgba(0, 0, 0, 0.3);
    color: var(--danger-red);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-blue);
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}
</style>

<div class="moderator-container">
    <div class="moderator-header">
        <h1>👥 Moderate Users</h1>
        <p style="color: var(--text-gray);">Manage user accounts and violations</p>
    </div>

    <div class="moderator-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="moderate-listings.php">📝 Moderate Listings</a>
        <a href="moderate-users.php" class="active">👥 Moderate Users</a>
        <a href="moderate-reports.php">🚨 Reports</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($flagged_users); ?></div>
            <div class="stat-label">Flagged Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($suspended_users); ?></div>
            <div class="stat-label">Currently Suspended</div>
        </div>
    </div>

    <?php if(count($flagged_users) > 0): ?>
    <div class="moderator-section">
        <h2>🚩 Flagged Users (<?php echo count($flagged_users); ?>)</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Reports</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($flagged_users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span style="color: var(--danger-red); font-weight: bold;">
                                <?php echo $user['report_count']; ?> reports
                            </span>
                        </td>
                        <td><?php echo $user['last_activity'] ? date('M j, Y', strtotime($user['last_activity'])) : 'Never'; ?></td>
                        <td>
                            <a href="../profile.php?id=<?php echo $user['id']; ?>" 
                               class="action-btn view" target="_blank">View</a>
                            <button class="action-btn warn" onclick="showWarnModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                Warn
                            </button>
                            <button class="action-btn suspend" onclick="showSuspendModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                Suspend
                            </button>
                            <button class="action-btn ban" onclick="showBanModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                Ban
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if(count($suspended_users) > 0): ?>
    <div class="moderator-section">
        <h2>⏸️ Suspended Users (<?php echo count($suspended_users); ?>)</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Reason</th>
                        <th>Suspended By</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($suspended_users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['suspension_reason']); ?></td>
                        <td><?php echo htmlspecialchars($user['moderator_name']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['suspension_end'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="unsuspend_user" class="action-btn view">
                                    Unsuspend
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="moderator-section">
        <h2>Recent Actions</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Action</th>
                        <th>Reason</th>
                        <th>Moderator</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_actions as $action): ?>
                    <tr>
                        <td><?php echo $action['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($action['username']); ?></td>
                        <td>
                            <span style="color: var(--danger-red);">
                                <?php echo ucfirst($action['action']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($action['reason']); ?></td>
                        <td><?php echo htmlspecialchars($action['moderator_name']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($action['action_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Warn Modal -->
<div id="warnModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">⚠️ Warn User: <span id="warnUsername"></span></h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="warnUserId">
            <div class="form-group">
                <label>Warning Message</label>
                <textarea name="warning_message" rows="5" required placeholder="Explain the violation and consequences..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeWarnModal()">Cancel</button>
                <button type="submit" name="warn_user" class="btn-primary btn-block">Send Warning</button>
            </div>
        </form>
    </div>
</div>

<!-- Suspend Modal -->
<div id="suspendModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem; color: var(--warning-orange);">⏸️ Suspend User: <span id="suspendUsername"></span></h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="suspendUserId">
            <div class="form-group">
                <label>Suspension Duration</label>
                <select name="duration_days" required>
                    <option value="1">1 Day</option>
                    <option value="3">3 Days</option>
                    <option value="7" selected>7 Days</option>
                    <option value="14">14 Days</option>
                    <option value="30">30 Days</option>
                </select>
            </div>
            <div class="form-group">
                <label>Suspension Reason</label>
                <textarea name="suspension_reason" rows="4" required placeholder="Reason for suspension..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeSuspendModal()">Cancel</button>
                <button type="submit" name="suspend_user" class="btn-danger btn-block">Suspend User</button>
            </div>
        </form>
    </div>
</div>

<!-- Ban Modal -->
<div id="banModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem; color: var(--danger-red);">🚫 Ban User: <span id="banUsername"></span></h3>
        <p style="color: var(--text-gray); margin-bottom: 1.5rem;">
            This is a permanent action! The user will be banned and cannot access the platform.
        </p>
        <form method="POST">
            <input type="hidden" name="user_id" id="banUserId">
            <div class="form-group">
                <label>Ban Reason</label>
                <textarea name="ban_reason" rows="4" required placeholder="Reason for permanent ban..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeBanModal()">Cancel</button>
                <button type="submit" name="ban_user" class="btn-danger btn-block">Ban Permanently</button>
            </div>
        </form>
    </div>
</div>

<script>
function showWarnModal(userId, username) {
    document.getElementById('warnUserId').value = userId;
    document.getElementById('warnUsername').textContent = username;
    document.getElementById('warnModal').style.display = 'flex';
}

function closeWarnModal() {
    document.getElementById('warnModal').style.display = 'none';
}

function showSuspendModal(userId, username) {
    document.getElementById('suspendUserId').value = userId;
    document.getElementById('suspendUsername').textContent = username;
    document.getElementById('suspendModal').style.display = 'flex';
}

function closeSuspendModal() {
    document.getElementById('suspendModal').style.display = 'none';
}

function showBanModal(userId, username) {
    document.getElementById('banUserId').value = userId;
    document.getElementById('banUsername').textContent = username;
    document.getElementById('banModal').style.display = 'flex';
}

function closeBanModal() {
    document.getElementById('banModal').style.display = 'none';
}

// Close modals on outside click
['warnModal', 'suspendModal', 'banModal'].forEach(modalId => {
    document.getElementById(modalId)?.addEventListener('click', function(e) {
        if(e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

<?php include '../views/footer.php'; ?>