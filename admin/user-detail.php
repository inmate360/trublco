<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
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

$success = '';
$error = '';

// Handle moderator toggle
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_moderator'])) {
    $target_user_id = $_POST['user_id'];
    
    $query = "UPDATE users SET is_moderator = NOT is_moderator WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $target_user_id);
    
    if($stmt->execute()) {
        $success = 'Moderator status updated!';
    } else {
        $error = 'Failed to update moderator status';
    }
}

// Get user details
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM listings WHERE user_id = u.id) as listing_count,
          (SELECT COUNT(*) FROM reports WHERE reported_type = 'user' AND reported_id = u.id) as report_count
          FROM users u
          WHERE u.id = :id
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$user = $stmt->fetch();

if(!$user) {
    header('Location: users.php');
    exit();
}

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<div class="admin-container">
    <div class="admin-header">
        <h1>👤 User Details: <?php echo htmlspecialchars($user['username']); ?></h1>
        <p style="color: var(--text-gray);"><a href="users.php">← Back to users</a></p>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>User Information</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div>
                <strong style="color: var(--primary-blue);">Username:</strong>
                <p style="margin-top: 0.5rem;"><?php echo htmlspecialchars($user['username']); ?></p>
            </div>
            <div>
                <strong style="color: var(--primary-blue);">Email:</strong>
                <p style="margin-top: 0.5rem;"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div>
                <strong style="color: var(--primary-blue);">Status:</strong>
                <p style="margin-top: 0.5rem;">
                    <?php if($user['is_admin']): ?>
                    <span style="color: var(--featured-gold);">🛡️ Administrator</span>
                    <?php elseif($user['is_moderator']): ?>
                    <span style="color: var(--primary-blue);">⚡ Moderator</span>
                    <?php elseif($user['is_suspended']): ?>
                    <span style="color: var(--danger-red);">🚫 Suspended</span>
                    <?php else: ?>
                    <span style="color: var(--success-green);">✓ Active</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <strong style="color: var(--primary-blue);">Joined:</strong>
                <p style="margin-top: 0.5rem;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
            </div>
            <div>
                <strong style="color: var(--primary-blue);">Total Listings:</strong>
                <p style="margin-top: 0.5rem;"><?php echo $user['listing_count']; ?></p>
            </div>
            <div>
                <strong style="color: var(--primary-blue);">Reports Against User:</strong>
                <p style="margin-top: 0.5rem;">
                    <span style="color: <?php echo $user['report_count'] > 0 ? 'var(--danger-red)' : 'var(--success-green)'; ?>">
                        <?php echo $user['report_count']; ?>
                    </span>
                </p>
            </div>
        </div>
    </div>

    <?php if(!$user['is_admin']): ?>
    <div class="admin-section">
        <h2>Moderator Status</h2>
        <p style="color: var(--text-gray); margin-bottom: 1.5rem;">
            Moderators can manage users, listings, and reports. They cannot access admin features like site settings or upgrades.
        </p>
        
        <form method="POST" action="">
            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
            <?php if($user['is_moderator']): ?>
            <button type="submit" name="toggle_moderator" class="btn-danger" onclick="return confirm('Remove moderator privileges from this user?');">
                Remove Moderator Status
            </button>
            <?php else: ?>
            <button type="submit" name="toggle_moderator" class="btn-success" onclick="return confirm('Grant moderator privileges to this user?');">
                Make Moderator
            </button>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>Quick Actions</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="../profile.php?id=<?php echo $user['id']; ?>" class="btn-primary" target="_blank">
                View Public Profile
            </a>
            <a href="user-listings.php?user_id=<?php echo $user['id']; ?>" class="btn-secondary">
                View User's Listings
            </a>
            <?php if($user['report_count'] > 0): ?>
            <a href="reports.php?user_id=<?php echo $user['id']; ?>" class="btn-danger">
                View Reports (<?php echo $user['report_count']; ?>)
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../views/footer.php'; ?>