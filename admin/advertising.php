<?php
session_start();
require_once '../config/database.php';
require_once '../classes/AdvertisingManager.php';
require_once '../classes/CSRF.php';

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

// Check if advertising tables exist
function tableExists($db, $tableName) {
    try {
        $result = $db->query("SHOW TABLES LIKE '{$tableName}'");
        return $result->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

if(!tableExists($db, 'premium_listings')) {
    // Show setup instructions
    include '../views/header.php';
    ?>
    <link rel="stylesheet" href="../assets/css/dark-blue-theme.css">
    
    <div class="page-content">
        <div class="container" style="max-width: 800px; margin: 4rem auto; text-align: center;">
            <div style="font-size: 5rem; margin-bottom: 1rem;">📊</div>
            <h1>Advertising System Not Setup</h1>
            <p style="color: var(--text-gray); margin: 2rem 0;">
                The advertising system requires additional database tables to be created.
            </p>
            
            <div class="card" style="text-align: left; padding: 2rem; background: rgba(245, 158, 11, 0.1); border: 2px solid var(--warning-orange);">
                <h3 style="color: var(--warning-orange); margin-bottom: 1rem;">⚙️ Setup Instructions</h3>
                <ol style="line-height: 2; margin-left: 1.5rem;">
                    <li>Access your database via phpMyAdmin or MySQL client</li>
                    <li>Run the SQL file: <code>database/advertising_system.sql</code></li>
                    <li>Refresh this page to access the advertising dashboard</li>
                </ol>
                
                <div style="margin-top: 2rem; padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
                    <strong>SQL File Location:</strong><br>
                    <code>/var/www/vhosts/turnpage.io/httpdocs/database/advertising_system.sql</code>
                </div>
            </div>
            
            <a href="/admin/dashboard.php" class="btn-secondary" style="margin-top: 2rem;">
                ← Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php
    include '../views/footer.php';
    exit();
}

$adManager = new AdvertisingManager($db);

$success = '';
$error = '';

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'add_adsense':
                $unit_name = trim($_POST['unit_name']);
                $placement_id = (int)$_POST['placement_id'];
                $client_id = trim($_POST['client_id']);
                $slot_id = trim($_POST['slot_id']);
                $ad_format = $_POST['ad_format'];
                
                $query = "INSERT INTO adsense_units (unit_name, placement_id, client_id, slot_id, ad_format, responsive)
                          VALUES (:name, :placement, :client, :slot, :format, TRUE)";
                
                try {
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $unit_name);
                    $stmt->bindParam(':placement', $placement_id);
                    $stmt->bindParam(':client', $client_id);
                    $stmt->bindParam(':slot', $slot_id);
                    $stmt->bindParam(':format', $ad_format);
                    
                    if($stmt->execute()) {
                        $success = 'AdSense unit added successfully!';
                    } else {
                        $error = 'Failed to add AdSense unit';
                    }
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_ad':
                $ad_type = $_POST['ad_type'];
                $ad_id = (int)$_POST['ad_id'];
                $is_active = (int)$_POST['is_active'];
                
                $table = '';
                switch($ad_type) {
                    case 'adsense': $table = 'adsense_units'; break;
                    case 'banner': $table = 'banner_ads'; break;
                    case 'native': $table = 'native_ads'; break;
                }
                
                if($table) {
                    $query = "UPDATE {$table} SET is_active = :active WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':active', $is_active);
                    $stmt->bindParam(':id', $ad_id);
                    $stmt->execute();
                    
                    $success = 'Ad status updated successfully!';
                }
                break;
        }
    }
}

// Get advertising statistics
$stats = [
    'total_premium' => 0,
    'active_premium' => 0,
    'total_sponsored' => 0,
    'active_sponsored' => 0,
    'total_banners' => 0,
    'active_banners' => 0,
    'total_adsense' => 0,
    'active_adsense' => 0,
    'impressions_today' => 0,
    'clicks_today' => 0,
    'revenue_today' => 0,
    'revenue_month' => 0
];

try {
    $query = "SELECT 
        (SELECT COUNT(*) FROM premium_listings) as total_premium,
        (SELECT COUNT(*) FROM premium_listings WHERE is_active = TRUE) as active_premium,
        (SELECT COUNT(*) FROM sponsored_profiles) as total_sponsored,
        (SELECT COUNT(*) FROM sponsored_profiles WHERE is_active = TRUE) as active_sponsored,
        (SELECT COUNT(*) FROM banner_ads) as total_banners,
        (SELECT COUNT(*) FROM banner_ads WHERE is_active = TRUE) as active_banners,
        (SELECT COUNT(*) FROM adsense_units) as total_adsense,
        (SELECT COUNT(*) FROM adsense_units WHERE is_active = TRUE) as active_adsense,
        (SELECT COUNT(*) FROM ad_impressions WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as impressions_today,
        (SELECT COUNT(*) FROM ad_clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as clicks_today,
        (SELECT COALESCE(SUM(cost), 0) FROM premium_listings WHERE DATE(created_at) = CURDATE()) as revenue_today,
        (SELECT COALESCE(SUM(cost), 0) FROM premium_listings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as revenue_month";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Error getting ad stats: " . $e->getMessage());
}

// Get ad placements
$placements = [];
try {
    $query = "SELECT * FROM ad_placements ORDER BY location, position";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $placements = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error getting placements: " . $e->getMessage());
}

// Get AdSense units
$adsense_units = [];
try {
    $query = "SELECT au.*, ap.placement_name 
              FROM adsense_units au
              LEFT JOIN ad_placements ap ON au.placement_id = ap.id
              ORDER BY au.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $adsense_units = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error getting AdSense units: " . $e->getMessage());
}

// Get banner ads
$banner_ads = [];
try {
    $query = "SELECT ba.*, ap.placement_name 
              FROM banner_ads ba
              LEFT JOIN ad_placements ap ON ba.placement_id = ap.id
              ORDER BY ba.created_at DESC
              LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $banner_ads = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error getting banner ads: " . $e->getMessage());
}

// Get premium listings
$premium_listings = [];
try {
    $query = "SELECT pl.*, l.title, u.username
              FROM premium_listings pl
              LEFT JOIN listings l ON pl.listing_id = l.id
              LEFT JOIN users u ON pl.user_id = u.id
              ORDER BY pl.created_at DESC
              LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $premium_listings = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error getting premium listings: " . $e->getMessage());
}

// Get sponsored profiles
$sponsored_profiles = [];
try {
    $query = "SELECT sp.*, u.username, u.email
              FROM sponsored_profiles sp
              LEFT JOIN users u ON sp.user_id = u.id
              ORDER BY sp.created_at DESC
              LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sponsored_profiles = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error getting sponsored profiles: " . $e->getMessage());
}

$csrf_token = CSRF::getToken();

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.ad-container {
    max-width: 1600px;
    margin: 2rem auto;
    padding: 0 20px;
}

.ad-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
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
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.ad-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
    overflow-x: auto;
}

.ad-tab {
    padding: 1rem 2rem;
    background: transparent;
    border: none;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.3s;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
}

.ad-tab.active {
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

.ad-tab:hover {
    color: var(--text-white);
}

.ad-content {
    display: none;
}

.ad-content.active {
    display: block;
}

.ad-section {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.ad-table {
    width: 100%;
    border-collapse: collapse;
}

.ad-table th {
    text-align: left;
    padding: 1rem;
    background: rgba(66, 103, 245, 0.1);
    color: var(--primary-blue);
    font-weight: 600;
}

.ad-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.ad-table tr:hover {
    background: rgba(66, 103, 245, 0.05);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-active {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-green);
}

.status-inactive {
    background: rgba(107, 114, 128, 0.2);
    color: var(--text-gray);
}

.status-expired {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .ad-table {
        font-size: 0.85rem;
    }
    
    .ad-table th,
    .ad-table td {
        padding: 0.5rem;
    }
}
</style>

<div class="page-content">
    <div class="ad-container">
        <div class="ad-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📊 Advertising Manager</h1>
                    <p style="color: var(--text-gray);">Manage ads, premium listings, and sponsored content</p>
                </div>
                <a href="/admin/dashboard.php" class="btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['revenue_today'], 2); ?></div>
                <div class="stat-label">Revenue Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['revenue_month'], 2); ?></div>
                <div class="stat-label">Revenue This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['impressions_today']); ?></div>
                <div class="stat-label">Impressions Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['clicks_today']); ?></div>
                <div class="stat-label">Clicks Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_premium']; ?> / <?php echo $stats['total_premium']; ?></div>
                <div class="stat-label">Premium Listings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_sponsored']; ?> / <?php echo $stats['total_sponsored']; ?></div>
                <div class="stat-label">Sponsored Profiles</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="ad-tabs">
            <button class="ad-tab active" onclick="switchTab('adsense')">
                Google AdSense (<?php echo $stats['active_adsense']; ?>)
            </button>
            <button class="ad-tab" onclick="switchTab('premium')">
                Premium Listings (<?php echo $stats['active_premium']; ?>)
            </button>
            <button class="ad-tab" onclick="switchTab('sponsored')">
                Sponsored Profiles (<?php echo $stats['active_sponsored']; ?>)
            </button>
            <button class="ad-tab" onclick="switchTab('banners')">
                Banner Ads (<?php echo $stats['active_banners']; ?>)
            </button>
            <button class="ad-tab" onclick="switchTab('placements')">
                Ad Placements
            </button>
        </div>

        <!-- AdSense Tab -->
        <div class="ad-content active" id="adsense-content">
            <div class="ad-section">
                <h2 style="margin-bottom: 1.5rem;">Google AdSense Units</h2>
                
                <button class="btn-primary" onclick="document.getElementById('addAdSenseForm').style.display='block'">
                    + Add AdSense Unit
                </button>
                
                <!-- Add AdSense Form -->
                <div id="addAdSenseForm" style="display: none; margin-top: 2rem; padding: 1.5rem; background: rgba(66, 103, 245, 0.05); border-radius: 12px;">
                    <h3 style="margin-bottom: 1rem;">Add New AdSense Unit</h3>
                    <form method="POST">
                        <?php echo CSRF::getHiddenInput(); ?>
                        <input type="hidden" name="action" value="add_adsense">
                        
                        <div class="form-group">
                            <label>Unit Name</label>
                            <input type="text" name="unit_name" required placeholder="e.g., Homepage Sidebar">
                        </div>
                        
                        <div class="form-group">
                            <label>Placement</label>
                            <select name="placement_id" required>
                                <option value="">Select placement...</option>
                                <?php foreach($placements as $placement): ?>
                                <option value="<?php echo $placement['id']; ?>">
                                    <?php echo htmlspecialchars($placement['placement_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Client ID (ca-pub-XXXXXXXXXXXXXXXX)</label>
                            <input type="text" name="client_id" required placeholder="ca-pub-1234567890123456">
                        </div>
                        
                        <div class="form-group">
                            <label>Slot ID</label>
                            <input type="text" name="slot_id" required placeholder="1234567890">
                        </div>
                        
                        <div class="form-group">
                            <label>Ad Format</label>
                            <select name="ad_format" required>
                                <option value="auto">Auto</option>
                                <option value="rectangle">Rectangle</option>
                                <option value="vertical">Vertical</option>
                                <option value="horizontal">Horizontal</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn-primary">Add Unit</button>
                            <button type="button" class="btn-secondary" onclick="document.getElementById('addAdSenseForm').style.display='none'">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- AdSense Units Table -->
                <?php if(count($adsense_units) > 0): ?>
                <div style="overflow-x: auto; margin-top: 2rem;">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>Unit Name</th>
                                <th>Placement</th>
                                <th>Client ID</th>
                                <th>Slot ID</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($adsense_units as $unit): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($unit['unit_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($unit['placement_name']); ?></td>
                                <td><code style="font-size: 0.85rem;"><?php echo htmlspecialchars($unit['client_id']); ?></code></td>
                                <td><code style="font-size: 0.85rem;"><?php echo htmlspecialchars($unit['slot_id']); ?></code></td>
                                <td><?php echo number_format($unit['impressions']); ?></td>
                                <td><?php echo number_format($unit['clicks']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $unit['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $unit['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?php echo CSRF::getHiddenInput(); ?>
                                        <input type="hidden" name="action" value="toggle_ad">
                                        <input type="hidden" name="ad_type" value="adsense">
                                        <input type="hidden" name="ad_id" value="<?php echo $unit['id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $unit['is_active'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn-secondary btn-small">
                                            <?php echo $unit['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-gray); padding: 3rem;">
                    No AdSense units configured yet
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Premium Listings Tab -->
        <div class="ad-content" id="premium-content">
            <div class="ad-section">
                <h2 style="margin-bottom: 1.5rem;">Premium Listings</h2>
                
                <?php if(count($premium_listings) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>Listing</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Cost</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>End Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($premium_listings as $pl): ?>
                            <?php 
                            $is_expired = strtotime($pl['end_date']) < time();
                            $status_class = $pl['is_active'] && !$is_expired ? 'active' : ($is_expired ? 'expired' : 'inactive');
                            ?>
                            <tr>
                                <td>
                                    <a href="/listing.php?id=<?php echo $pl['listing_id']; ?>" target="_blank" style="color: var(--primary-blue);">
                                        <?php echo htmlspecialchars($pl['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($pl['username']); ?></td>
                                <td><span style="text-transform: capitalize;"><?php echo htmlspecialchars($pl['placement_type']); ?></span></td>
                                <td><?php echo $pl['duration_days']; ?> days</td>
                                <td>$<?php echo number_format($pl['cost'], 2); ?></td>
                                <td><?php echo number_format($pl['current_impressions']); ?></td>
                                <td><?php echo number_format($pl['clicks']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($pl['end_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $status_class; ?>">
                                        <?php echo $is_expired ? 'Expired' : ($pl['is_active'] ? 'Active' : 'Inactive'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-gray); padding: 3rem;">
                    No premium listings yet
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sponsored Profiles Tab -->
        <div class="ad-content" id="sponsored-content">
            <div class="ad-section">
                <h2 style="margin-bottom: 1.5rem;">Sponsored Profiles</h2>
                
                <?php if(count($sponsored_profiles) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Boost Level</th>
                                <th>Duration</th>
                                <th>Cost</th>
                                <th>Profile Views</th>
                                <th>End Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($sponsored_profiles as $sp): ?>
                            <?php 
                            $is_expired = strtotime($sp['end_date']) < time();
                            $status_class = $sp['is_active'] && !$is_expired ? 'active' : ($is_expired ? 'expired' : 'inactive');
                            ?>
                            <tr>
                                <td>
                                    <a href="/profile.php?id=<?php echo $sp['user_id']; ?>" target="_blank" style="color: var(--primary-blue);">
                                        <?php echo htmlspecialchars($sp['username']); ?>
                                    </a>
                                </td>
                                <td>Level <?php echo $sp['boost_level']; ?></td>
                                <td><?php echo $sp['duration_days']; ?> days</td>
                                <td>$<?php echo number_format($sp['cost'], 2); ?></td>
                                <td><?php echo number_format($sp['profile_views']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($sp['end_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $status_class; ?>">
                                        <?php echo $is_expired ? 'Expired' : ($sp['is_active'] ? 'Active' : 'Inactive'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-gray); padding: 3rem;">
                    No sponsored profiles yet
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Banner Ads Tab -->
        <div class="ad-content" id="banners-content">
            <div class="ad-section">
                <h2 style="margin-bottom: 1.5rem;">Banner Ads</h2>
                
                <p style="text-align: center; color: var(--text-gray); padding: 3rem;">
                    Banner ad management coming soon
                </p>
            </div>
        </div>

        <!-- Ad Placements Tab -->
        <div class="ad-content" id="placements-content">
            <div class="ad-section">
                <h2 style="margin-bottom: 1.5rem;">Ad Placements</h2>
                
                <?php if(count($placements) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>Placement Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Position</th>
                                <th>Max Ads</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($placements as $placement): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($placement['placement_name']); ?></strong></td>
                                <td><span style="text-transform: capitalize;"><?php echo htmlspecialchars($placement['placement_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($placement['location']); ?></td>
                                <td><?php echo htmlspecialchars($placement['position']); ?></td>
                                <td><?php echo $placement['max_ads_per_page']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $placement['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $placement['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-gray); padding: 3rem;">
                    No ad placements configured
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    // Hide all content
    document.querySelectorAll('.ad-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active from all tabs
    document.querySelectorAll('.ad-tab').forEach(t => {
        t.classList.remove('active');
    });
    
    // Show selected content and activate tab
    document.getElementById(tab + '-content').classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php include '../views/footer.php'; ?>