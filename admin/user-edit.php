<?php
session_start();
require_once '../config/database.php';
require_once '../classes/SecurityLogger.php';
require_once '../includes/security_headers.php';
require_once '../classes/CSRF.php';

SecurityHeaders::set();

// Check if user is admin
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
$query = "SELECT is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->fetch();

if(!$admin || !$admin['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$securityLogger = new SecurityLogger($db);

$user_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if(!$user_id) {
    header('Location: users.php');
    exit();
}

// Get user data
$query = "SELECT * FROM users WHERE id = :id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

if(!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: users.php');
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        if(isset($_POST['update_user'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $is_moderator = isset($_POST['is_moderator']) ? 1 : 0;
            $is_suspended = isset($_POST['is_suspended']) ? 1 : 0;
            $is_banned = isset($_POST['is_banned']) ? 1 : 0;
            $email_verified = isset($_POST['email_verified']) ? 1 : 0;
            
            // Validate
            if(empty($username) || empty($email)) {
                $error = 'Username and email are required';
            } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
            } else {
                // Check if username/email already exists (excluding current user)
                $query = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                
                if($stmt->rowCount() > 0) {
                    $error = 'Username or email already exists';
                } else {
                    // Update user
                    $query = "UPDATE users SET 
                              username = :username,
                              email = :email,
                              is_admin = :is_admin,
                              is_moderator = :is_moderator,
                              is_suspended = :is_suspended,
                              is_banned = :is_banned,
                              email_verified = :email_verified
                              WHERE id = :user_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':is_admin', $is_admin);
                    $stmt->bindParam(':is_moderator', $is_moderator);
                    $stmt->bindParam(':is_suspended', $is_suspended);
                    $stmt->bindParam(':is_banned', $is_banned);
                    $stmt->bindParam(':email_verified', $email_verified);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if($stmt->execute()) {
                        $securityLogger->log('user_updated', "Updated user ID: {$user_id} by admin", 'low');
                        $success = 'User updated successfully!';
                        
                        // Refresh user data
                        $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $user_id);
                        $stmt->execute();
                        $user = $stmt->fetch();
                    } else {
                        $error = 'Failed to update user';
                    }
                }
            }
        } elseif(isset($_POST['reset_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if(empty($new_password) || empty($confirm_password)) {
                $error = 'Please enter and confirm the new password';
            } elseif(strlen($new_password) < 8) {
                $error = 'Password must be at least 8 characters';
            } elseif($new_password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
                
                $query = "UPDATE users SET password = :password WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $user_id);
                
                if($stmt->execute()) {
                    $securityLogger->log('admin_password_reset', "Reset password for user ID: {$user_id}", 'medium');
                    $success = 'Password reset successfully!';
                } else {
                    $error = 'Failed to reset password';
                }
            }
        } elseif(isset($_POST['delete_user'])) {
            // Delete all user's content first
            $db->exec("DELETE FROM listings WHERE user_id = {$user_id}");
            $db->exec("DELETE FROM messages WHERE sender_id = {$user_id} OR receiver_id = {$user_id}");
            $db->exec("DELETE FROM reports WHERE reporter_id = {$user_id}");
            
            // Delete user
            $query = "DELETE FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if($stmt->execute()) {
                $securityLogger->log('user_deleted', "Deleted user ID: {$user_id} by admin", 'high');
                $_SESSION['success'] = 'User deleted successfully!';
                header('Location: users.php');
                exit();
            } else {
                $error = 'Failed to delete user';
            }
        }
    }
}

// Get user statistics
$query = "SELECT 
          (SELECT COUNT(*) FROM listings WHERE user_id = :user_id1) as listing_count,
          (SELECT COUNT(*) FROM messages WHERE sender_id = :user_id2) as message_count,
          (SELECT COUNT(*) FROM reports WHERE reported_id = :user_id3 AND reported_type = 'user') as report_count";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id1', $user_id);
$stmt->bindParam(':user_id2', $user_id);
$stmt->bindParam(':user_id3', $user_id);
$stmt->execute();
$stats = $stmt->fetch();

// Get recent activity
$query = "SELECT * FROM security_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$activity_logs = $stmt->fetchAll();

// Generate CSRF token
$csrf_token = CSRF::getToken();

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.admin-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.admin-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.admin-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.admin-section h2 {
    margin-bottom: 1.5rem;
    color: var(--primary-blue);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(66, 103, 245, 0.05);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-blue);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.user-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-right: 0.5rem;
}

.badge-admin {
    background: rgba(251, 191, 36, 0.2);
    color: var(--featured-gold);
}

.badge-moderator {
    background: rgba(66, 103, 245, 0.2);
    color: var(--primary-blue);
}

.badge-verified {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-green);
}

.badge-suspended {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

.badge-banned {
    background: rgba(0, 0, 0, 0.3);
    color: var(--danger-red);
}

.activity-log {
    padding: 0.75rem;
    margin: 0.5rem 0;
    background: rgba(66, 103, 245, 0.05);
    border-left: 3px solid var(--primary-blue);
    border-radius: 8px;
}

.danger-zone {
    border: 2px solid var(--danger-red);
    background: rgba(239, 68, 68, 0.05);
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>✏️ Edit User: <?php echo htmlspecialchars($user['username']); ?></h1>
                <p style="color: var(--text-gray);">User ID: <?php echo $user['id']; ?></p>
            </div>
            <a href="users.php" class="btn-secondary">← Back to Users</a>
        </div>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- User Status Badges -->
    <div style="margin-bottom: 2rem;">
        <?php if($user['is_admin']): ?>
        <span class="user-badge badge-admin">🛡️ Admin</span>
        <?php endif; ?>
        <?php if($user['is_moderator']): ?>
        <span class="user-badge badge-moderator">👮 Moderator</span>
        <?php endif; ?>
        <?php if($user['email_verified']): ?>
        <span class="user-badge badge-verified">✓ Verified</span>
        <?php endif; ?>
        <?php if($user['is_suspended']): ?>
        <span class="user-badge badge-suspended">⏸️ Suspended</span>
        <?php endif; ?>
        <?php if($user['is_banned']): ?>
        <span class="user-badge badge-banned">🚫 Banned</span>
        <?php endif; ?>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['listing_count']; ?></div>
            <div class="stat-label">Total Listings</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['message_count']; ?></div>
            <div class="stat-label">Messages Sent</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['report_count']; ?></div>
            <div class="stat-label">Reports Against</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?></div>
            <div class="stat-label">Last Login</div>
        </div>
    </div>

    <!-- Edit User Form -->
    <div class="admin-section">
        <h2>👤 User Information</h2>
        <form method="POST" action="">
            <?php echo CSRF::getHiddenInput(); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required 
                           value="<?php echo htmlspecialchars($user['username']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Registration Date</label>
                <input type="text" value="<?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?>" disabled>
            </div>

            <div class="form-group">
                <label>Last IP Address</label>
                <input type="text" value="<?php echo htmlspecialchars($user['last_ip'] ?? 'N/A'); ?>" disabled>
            </div>

            <h3 style="margin: 2rem 0 1rem; color: var(--primary-blue);">Permissions & Status</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                    <input type="checkbox" name="is_admin" <?php echo $user['is_admin'] ? 'checked' : ''; ?>>
                    Admin Access
                </label>
                
                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                    <input type="checkbox" name="is_moderator" <?php echo $user['is_moderator'] ? 'checked' : ''; ?>>
                    Moderator Access
                </label>
                
                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                    <input type="checkbox" name="email_verified" <?php echo $user['email_verified'] ? 'checked' : ''; ?>>
                    Email Verified
                </label>
                
                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                    <input type="checkbox" name="is_suspended" <?php echo $user['is_suspended'] ? 'checked' : ''; ?>>
                    Suspended
                </label>
                
                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                    <input type="checkbox" name="is_banned" <?php echo $user['is_banned'] ? 'checked' : ''; ?>>
                    Banned
                </label>
            </div>

            <button type="submit" name="update_user" class="btn-primary" style="margin-top: 1.5rem;">
                💾 Save Changes
            </button>
        </form>
    </div>

    <!-- Reset Password -->
    <div class="admin-section">
        <h2>🔑 Reset Password</h2>
        <form method="POST" action="">
            <?php echo CSRF::getHiddenInput(); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" minlength="8" 
                           placeholder="Enter new password">
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" 
                           placeholder="Confirm new password">
                </div>
            </div>

            <button type="submit" name="reset_password" class="btn-warning">
                🔄 Reset Password
            </button>
        </form>
    </div>

    <!-- Recent Activity -->
    <?php if(count($activity_logs) > 0): ?>
    <div class="admin-section">
        <h2>📊 Recent Activity</h2>
        <?php foreach($activity_logs as $log): ?>
        <div class="activity-log">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                <span style="color: var(--text-gray); font-size: 0.85rem;">
                    <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                </span>
            </div>
            <div style="color: var(--text-gray); font-size: 0.9rem;">
                IP: <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                | Severity: <?php echo ucfirst($log['severity']); ?>
            </div>
            <?php if($log['details']): ?>
            <div style="color: var(--text-gray); font-size: 0.85rem; margin-top: 0.3rem;">
                <?php echo htmlspecialchars($log['details']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Danger Zone -->
    <div class="admin-section danger-zone">
        <h2 style="color: var(--danger-red);">⚠️ Danger Zone</h2>
        <p style="color: var(--text-gray); margin-bottom: 1.5rem;">
            Deleting a user is permanent and cannot be undone. All their listings, messages, and data will be deleted.
        </p>
        
        <form method="POST" action="" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone!');">
            <?php echo CSRF::getHiddenInput(); ?>
            <button type="submit" name="delete_user" class="btn-danger">
                🗑️ Delete User Permanently
            </button>
        </form>
    </div>

    <!-- Quick Links -->
    <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
        <a href="../profile.php?id=<?php echo $user['id']; ?>" target="_blank" class="btn-secondary">
            View Public Profile
        </a>
        <a href="users.php" class="btn-secondary">
            ← Back to Users List
        </a>
    </div>
</div>

<?php include '../views/footer.php'; ?>