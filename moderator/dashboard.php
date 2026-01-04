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

// Check if user is moderator or admin
$moderator = new Moderator($db);
if(!$moderator->isModerator($_SESSION['user_id']) && !$moderator->isAdmin($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get statistics
$stats = [];

// Total users
try {
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['count'];
} catch(PDOException $e) {
    $stats['total_users'] = 0;
}

// Suspended users
try {
    // Check if column exists first
    $check = "SHOW COLUMNS FROM users LIKE 'is_suspended'";
    $stmt = $db->prepare($check);
    $stmt->execute();
    
    if($stmt->rowCount() > 0) {
        $query = "SELECT COUNT(*) as count FROM users WHERE is_suspended = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['suspended_users'] = $stmt->fetch()['count'];
    } else {
        $stats['suspended_users'] = 0;
    }
} catch(PDOException $e) {
    $stats['suspended_users'] = 0;
}

// Pending listings
try {
    $query = "SELECT COUNT(*) as count FROM listings WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_listings'] = $stmt->fetch()['count'];
} catch(PDOException $e) {
    $stats['pending_listings'] = 0;
}

// Pending reports
try {
    $query = "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_reports'] = $stmt->fetch()['count'];
} catch(PDOException $e) {
    $stats['pending_reports'] = 0;
}

// Total listings
try {
    $query = "SELECT COUNT(*) as count FROM listings";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_listings'] = $stmt->fetch()['count'];
} catch(PDOException $e) {
    $stats['total_listings'] = 0;
}

// Recent reports
try {
    $query = "SELECT r.*, u.username as reporter_name,
              CASE 
                WHEN r.reported_type = 'listing' THEN (SELECT title FROM listings WHERE id = r.reported_id)
                WHEN r.reported_type = 'user' THEN (SELECT username FROM users WHERE id = r.reported_id)
                ELSE 'N/A'
              END as reported_item
              FROM reports r
              LEFT JOIN users u ON r.reporter_id = u.id
              WHERE r.status = 'pending'
              ORDER BY r.created_at DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_reports = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching reports: " . $e->getMessage());
    $recent_reports = [];
}

// Recent pending listings
try {
    $query = "SELECT l.*, u.username, c.name as category_name
              FROM listings l
              LEFT JOIN users u ON l.user_id = u.id
              LEFT JOIN categories c ON l.category_id = c.id
              WHERE l.status = 'pending'
              ORDER BY l.created_at DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_listings_list = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching pending listings: " . $e->getMessage());
    $pending_listings_list = [];
}

include '../views/header.php';
?>

<style>
.mod-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.mod-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.mod-nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.mod-nav a {
    padding: 0.75rem 1.5rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-white);
    transition: all 0.3s;
}

.mod-nav a:hover, .mod-nav a.active {
    background: rgba(66, 103, 245, 0.1);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(66, 103, 245, 0.2);
    border-color: var(--primary-blue);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-blue);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.mod-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.mod-section h2 {
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
}

.action-btn.view {
    background: rgba(6, 182, 212, 0.2);
    color: var(--info-cyan);
}

.action-btn.approve {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-green);
}

.action-btn.reject {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .mod-nav {
        flex-direction: column;
    }
    
    .data-table {
        font-size: 0.85rem;
    }
}
</style>

<div class="mod-container">
    <div class="mod-header">
        <h1>🛡️ Moderator Dashboard</h1>
        <p style="color: var(--text-gray);">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </div>

    <div class="mod-nav">
        <a href="dashboard.php" class="active">📊 Dashboard</a>
        <a href="moderate-listings.php">📝 Moderate Listings (<?php echo $stats['pending_listings']; ?>)</a>
        <a href="moderate-reports.php">🚨 Reports (<?php echo $stats['pending_reports']; ?>)</a>
        <a href="moderate-users.php">👥 Users</a>
        <?php if($moderator->isAdmin($_SESSION['user_id'])): ?>
        <a href="../admin/dashboard.php" style="color: var(--featured-gold);">⚡ Admin Panel</a>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            <div class="stat-label">Total Users</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo number_format($stats['total_listings']); ?></div>
            <div class="stat-label">Total Listings</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo number_format($stats['pending_listings']); ?></div>
            <div class="stat-label">Pending Listings</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?php echo number_format($stats['pending_reports']); ?></div>
            <div class="stat-label">Pending Reports</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🚫</div>
            <div class="stat-value"><?php echo number_format($stats['suspended_users']); ?></div>
            <div class="stat-label">Suspended Users</div>
        </div>
    </div>

    <?php if(count($pending_listings_list) > 0): ?>
    <div class="mod-section">
        <h2>⏳ Pending Listings</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_listings_list as $listing): ?>
                    <tr>
                        <td><?php echo $listing['id']; ?></td>
                        <td><?php echo htmlspecialchars(substr($listing['title'], 0, 50)); ?>...</td>
                        <td><?php echo htmlspecialchars($listing['username']); ?></td>
                        <td><?php echo htmlspecialchars($listing['category_name']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
                        <td>
                            <a href="../listing.php?id=<?php echo $listing['id']; ?>" class="action-btn view" target="_blank">View</a>
                            <a href="approve-listing.php?id=<?php echo $listing['id']; ?>" class="action-btn approve">Approve</a>
                            <a href="reject-listing.php?id=<?php echo $listing['id']; ?>" class="action-btn reject">Reject</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if(count($recent_reports) > 0): ?>
    <div class="mod-section">
        <h2>🚨 Recent Reports</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_reports as $report): ?>
                    <tr>
                        <td><?php echo $report['id']; ?></td>
                        <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                        <td><?php echo ucfirst($report['reported_type']); ?></td>
                        <td><?php echo htmlspecialchars(substr($report['reported_item'] ?? 'N/A', 0, 30)); ?></td>
                        <td><?php echo htmlspecialchars($report['reason']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                        <td>
                            <a href="view-report.php?id=<?php echo $report['id']; ?>" class="action-btn view">Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if(count($pending_listings_list) == 0 && count($recent_reports) == 0): ?>
    <div class="mod-section" style="text-align: center; padding: 4rem 2rem;">
        <div style="font-size: 5rem; margin-bottom: 1rem;">✅</div>
        <h2>All Caught Up!</h2>
        <p style="color: var(--text-gray);">No pending items to review at the moment.</p>
    </div>
    <?php endif; ?>
</div>

<?php include '../views/footer.php'; ?>