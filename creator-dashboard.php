<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if user is a creator
$query = "SELECT creator FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$isCreator = $stmt->fetchColumn();

if(!$isCreator) {
    header('Location: become-creator.php');
    exit();
}

// Get dashboard stats
$statsQuery = "SELECT 
    (SELECT COUNT(*) FROM creator_listings WHERE creator_id = :user_id AND status = 'active') as active_listings,
    (SELECT COUNT(*) FROM creator_purchases WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id)) as total_sales,
    (SELECT COALESCE(SUM(amount), 0) FROM creator_purchases WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id)) as total_earnings,
    (SELECT SUM(views) FROM creator_listings WHERE creator_id = :user_id) as total_views,
    (SELECT COUNT(*) FROM creator_reviews WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id)) as total_reviews,
    (SELECT AVG(rating) FROM creator_reviews WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id)) as avg_rating";

$stmt = $db->prepare($statsQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent listings
$listingsQuery = "SELECT * FROM creator_listings WHERE creator_id = :user_id ORDER BY created_at DESC LIMIT 5";
$stmt = $db->prepare($listingsQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$recentListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent sales
$salesQuery = "SELECT cp.*, cl.title, cl.thumbnail 
               FROM creator_purchases cp
               LEFT JOIN creator_listings cl ON cp.listing_id = cl.id
               WHERE cl.creator_id = :user_id
               ORDER BY cp.purchased_at DESC
               LIMIT 10";
$stmt = $db->prepare($salesQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$showWelcome = isset($_GET['welcome']);

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root {
    --color-canvas-default: #0d1117;
    --color-canvas-subtle: #161b22;
    --color-canvas-overlay: #1c2128;
    --color-border-default: #30363d;
    --color-border-muted: #21262d;
    --color-text-primary: #e6edf3;
    --color-text-secondary: #7d8590;
    --color-text-tertiary: #636e7b;
    --color-accent-emphasis: #1f6feb;
    --color-accent-muted: rgba(31, 111, 235, 0.15);
    --color-success-emphasis: #238636;
    --color-attention-emphasis: #9e6a03;
    --color-danger-emphasis: #da3633;
    --color-done-emphasis: #8957e5;
    --color-shadow: rgba(1, 4, 9, 0.85);
}

body {
    background: var(--color-canvas-default);
    color: var(--color-text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
}

.dashboard-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.dashboard-header {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px var(--color-shadow);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--color-done-emphasis), var(--color-accent-emphasis));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.header-title h1 {
    font-size: 2rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
    font-size: 0.875rem;
}

.btn-primary {
    background: var(--color-accent-emphasis);
    color: white;
}

.btn-primary:hover {
    background: #1a5cd7;
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--color-canvas-overlay);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border-default);
}

.btn-secondary:hover {
    border-color: var(--color-accent-emphasis);
}

.nav-tabs {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid var(--color-border-default);
}

.nav-tab {
    padding: 0.75rem 1.25rem;
    color: var(--color-text-secondary);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    font-weight: 500;
}

.nav-tab:hover {
    color: var(--color-text-primary);
}

.nav-tab.active {
    color: var(--color-accent-emphasis);
    border-bottom-color: var(--color-accent-emphasis);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.stat-card:hover {
    border-color: var(--color-accent-emphasis);
    transform: translateY(-2px);
    box-shadow: 0 12px 32px var(--color-shadow);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    font-weight: 600;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin-bottom: 0.5rem;
}

.stat-change {
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-change.positive {
    color: var(--color-success-emphasis);
}

.stat-change.negative {
    color: var(--color-danger-emphasis);
}

.content-section {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: var(--color-canvas-overlay);
}

.data-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 1rem;
    border-top: 1px solid var(--color-border-muted);
    color: var(--color-text-secondary);
}

.data-table tr:hover {
    background: var(--color-canvas-overlay);
}

.listing-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.listing-thumb {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--color-border-default);
}

.listing-info h4 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0 0 0.25rem 0;
}

.listing-info p {
    font-size: 0.75rem;
    color: var(--color-text-tertiary);
    margin: 0;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-active {
    background: rgba(35, 134, 54, 0.15);
    color: var(--color-success-emphasis);
}

.status-pending {
    background: rgba(158, 106, 3, 0.15);
    color: var(--color-attention-emphasis);
}

.welcome-banner {
    background: linear-gradient(135deg, var(--color-done-emphasis), var(--color-accent-emphasis));
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    text-align: center;
}

.welcome-banner h2 {
    color: white;
    font-size: 1.75rem;
    margin: 0 0 0.5rem 0;
}

.welcome-banner p {
    color: rgba(255,255,255,0.9);
    margin: 0 0 1.5rem 0;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .header-top {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="dashboard-page">
    <div class="dashboard-container">
        <?php if($showWelcome): ?>
        <div class="welcome-banner">
            <h2>🎉 Welcome to the Creator Program!</h2>
            <p>You're all set! Start uploading your content and earning money.</p>
            <a href="creator-upload.php" class="btn btn-primary" style="background: white; color: var(--color-accent-emphasis);">
                <i class="bi bi-plus-circle"></i>
                Upload Your First Content
            </a>
        </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <div>
                        <h1>Creator Dashboard</h1>
                        <p style="color: var(--color-text-secondary); margin: 0.25rem 0 0 0;">
                            Manage your content and track performance
                        </p>
                    </div>
                </div>
                
                <div class="header-actions">
                    <a href="creator-upload.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i>
                        New Listing
                    </a>
                    <a href="marketplace.php" class="btn btn-secondary">
                        <i class="bi bi-shop"></i>
                        View Marketplace
                    </a>
                </div>
            </div>
            
            <div class="nav-tabs">
                <a href="creator-dashboard.php" class="nav-tab active">
                    <i class="bi bi-house-fill"></i> Overview
                </a>
                <a href="creator-listings.php" class="nav-tab">
                    <i class="bi bi-grid"></i> Listings
                </a>
                <a href="creator-analytics.php" class="nav-tab">
                    <i class="bi bi-graph-up"></i> Analytics
                </a>
                <a href="creator-earnings.php" class="nav-tab">
                    <i class="bi bi-currency-dollar"></i> Earnings
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Listings</span>
                    <div class="stat-icon" style="background: var(--color-accent-muted); color: var(--color-accent-emphasis);">
                        <i class="bi bi-grid"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_listings']); ?></div>
                <div class="stat-change positive">
                    <i class="bi bi-arrow-up"></i> Live now
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Sales</span>
                    <div class="stat-icon" style="background: rgba(35, 134, 54, 0.15); color: var(--color-success-emphasis);">
                        <i class="bi bi-bag-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_sales']); ?></div>
                <div class="stat-change positive">
                    <i class="bi bi-arrow-up"></i> All time
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Earnings</span>
                    <div class="stat-icon" style="background: rgba(137, 87, 229, 0.15); color: var(--color-done-emphasis);">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                </div>
                <div class="stat-value">$<?php echo number_format($stats['total_earnings'], 2); ?></div>
                <div class="stat-change positive">
                    <i class="bi bi-arrow-up"></i> Revenue
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Views</span>
                    <div class="stat-icon" style="background: rgba(158, 106, 3, 0.15); color: var(--color-attention-emphasis);">
                        <i class="bi bi-eye"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                <div class="stat-change positive">
                    <i class="bi bi-arrow-up"></i> Impressions
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-grid"></i>
                    Recent Listings
                </h2>
                <a href="creator-listings.php" class="btn btn-secondary btn-sm">View All</a>
            </div>

            <?php if(count($recentListings) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Price</th>
                        <th>Views</th>
                        <th>Sales</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recentListings as $listing): ?>
                    <tr>
                        <td>
                            <div class="listing-item">
                                <img src="<?php echo htmlspecialchars($listing['thumbnail'] ?? '/assets/images/default-content.jpg'); ?>" 
                                     alt="" class="listing-thumb">
                                <div class="listing-info">
                                    <h4><?php echo htmlspecialchars($listing['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($listing['category'] ?? 'Uncategorized'); ?></p>
                                </div>
                            </div>
                        </td>
                        <td style="color: var(--color-text-primary); font-weight: 600;">
                            $<?php echo number_format($listing['price'], 2); ?>
                        </td>
                        <td><?php echo number_format($listing['views'] ?? 0); ?></td>
                        <td>
                            <?php
                            $salesQuery = "SELECT COUNT(*) FROM creator_purchases WHERE listing_id = :listing_id";
                            $stmt = $db->prepare($salesQuery);
                            $stmt->bindParam(':listing_id', $listing['id']);
                            $stmt->execute();
                            echo number_format($stmt->fetchColumn());
                            ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $listing['status']; ?>">
                                <?php echo ucfirst($listing['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="creator-edit-listing.php?id=<?php echo $listing['id']; ?>" 
                               style="color: var(--color-accent-emphasis); text-decoration: none;">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-text-secondary);">
                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 1rem;"></i>
                <p>No listings yet. Create your first listing to get started!</p>
                <a href="creator-upload.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="bi bi-plus-circle"></i>
                    Create Listing
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-clock-history"></i>
                    Recent Sales
                </h2>
                <a href="creator-earnings.php" class="btn btn-secondary btn-sm">View All</a>
            </div>

            <?php if(count($recentSales) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recentSales as $sale): ?>
                    <tr>
                        <td>
                            <div class="listing-item">
                                <img src="<?php echo htmlspecialchars($sale['thumbnail'] ?? '/assets/images/default-content.jpg'); ?>" 
                                     alt="" class="listing-thumb">
                                <div class="listing-info">
                                    <h4><?php echo htmlspecialchars($sale['title']); ?></h4>
                                </div>
                            </div>
                        </td>
                        <td style="color: var(--color-success-emphasis); font-weight: 700;">
                            $<?php echo number_format($sale['amount'], 2); ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($sale['purchased_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $sale['status']; ?>">
                                <?php echo ucfirst($sale['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-text-secondary);">
                <i class="bi bi-cart-x" style="font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 1rem;"></i>
                <p>No sales yet. Keep promoting your content!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
