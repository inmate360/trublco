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

// Get filter
$status = $_GET['status'] ?? 'all';

// Build query
$query = "SELECT cl.*, 
          (SELECT COUNT(*) FROM creator_purchases WHERE listing_id = cl.id) as sales_count,
          (SELECT SUM(amount) FROM creator_purchases WHERE listing_id = cl.id) as total_revenue
          FROM creator_listings cl 
          WHERE cl.creator_id = :user_id";

if($status !== 'all') {
    $query .= " AND cl.status = :status";
}

$query .= " ORDER BY cl.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
if($status !== 'all') {
    $stmt->bindParam(':status', $status);
}
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

.listings-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.listings-container {
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
    text-decoration: none;
}

.nav-tab:hover {
    color: var(--color-text-primary);
}

.nav-tab.active {
    color: var(--color-accent-emphasis);
    border-bottom-color: var(--color-accent-emphasis);
}

.filter-bar {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
}

.filter-tab {
    padding: 0.5rem 1rem;
    background: transparent;
    border: 1px solid var(--color-border-default);
    border-radius: 6px;
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 0.875rem;
}

.filter-tab:hover {
    border-color: var(--color-accent-emphasis);
    color: var(--color-text-primary);
}

.filter-tab.active {
    background: var(--color-accent-emphasis);
    border-color: var(--color-accent-emphasis);
    color: white;
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.listing-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
}

.listing-card:hover {
    border-color: var(--color-accent-emphasis);
    transform: translateY(-4px);
    box-shadow: 0 12px 32px var(--color-shadow);
}

.card-thumbnail {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: var(--color-canvas-overlay);
}

.card-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-status {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-active {
    background: var(--color-success-emphasis);
    color: white;
}

.status-pending {
    background: var(--color-attention-emphasis);
    color: white;
}

.status-inactive {
    background: var(--color-text-tertiary);
    color: white;
}

.card-body {
    padding: 1.25rem;
}

.card-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0 0 0.5rem 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.card-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.meta-item {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
}

.meta-value {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin-top: 0.25rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    flex: 1;
}

.btn-danger {
    background: var(--color-danger-emphasis);
    color: white;
}

.btn-danger:hover {
    background: #b52d2a;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
}

.empty-icon {
    font-size: 4rem;
    color: var(--color-text-tertiary);
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="listings-page">
    <div class="listings-container">
        <div class="dashboard-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-grid"></i>
                    </div>
                    <div>
                        <h1>My Listings</h1>
                        <p style="color: var(--color-text-secondary); margin: 0.25rem 0 0 0;">
                            Manage your content listings
                        </p>
                    </div>
                </div>
                
                <a href="creator-upload.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    New Listing
                </a>
            </div>
            
            <div class="nav-tabs">
                <a href="creator-dashboard.php" class="nav-tab">
                    <i class="bi bi-house-fill"></i> Overview
                </a>
                <a href="creator-listings.php" class="nav-tab active">
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

        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status == 'all' ? 'active' : ''; ?>">
                    All (<?php echo count($listings); ?>)
                </a>
                <a href="?status=active" class="filter-tab <?php echo $status == 'active' ? 'active' : ''; ?>">
                    Active
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status == 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=inactive" class="filter-tab <?php echo $status == 'inactive' ? 'active' : ''; ?>">
                    Inactive
                </a>
            </div>
            
            <div style="color: var(--color-text-secondary); font-size: 0.875rem;">
                <?php echo count($listings); ?> listings
            </div>
        </div>

        <?php if(count($listings) > 0): ?>
        <div class="listings-grid">
            <?php foreach($listings as $listing): ?>
            <div class="listing-card">
                <div class="card-thumbnail">
                    <img src="<?php echo htmlspecialchars($listing['thumbnail'] ?? '/assets/images/default-content.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($listing['title']); ?>">
                    <span class="card-status status-<?php echo $listing['status']; ?>">
                        <?php echo ucfirst($listing['status']); ?>
                    </span>
                </div>
                
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlspecialchars($listing['title']); ?></h3>
                    
                    <div class="card-meta">
                        <div class="meta-item">
                            <i class="bi bi-currency-dollar"></i> Price
                            <span class="meta-value">$<?php echo number_format($listing['price'], 2); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="bi bi-eye"></i> Views
                            <span class="meta-value"><?php echo number_format($listing['views'] ?? 0); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="bi bi-bag-check"></i> Sales
                            <span class="meta-value"><?php echo number_format($listing['sales_count']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="bi bi-graph-up"></i> Revenue
                            <span class="meta-value" style="color: var(--color-success-emphasis);">
                                $<?php echo number_format($listing['total_revenue'] ?? 0, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <a href="listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <a href="creator-edit-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 style="color: var(--color-text-primary); margin-bottom: 0.5rem;">No listings found</h3>
            <p style="color: var(--color-text-secondary); margin-bottom: 1.5rem;">
                <?php echo $status == 'all' ? 'Create your first listing to get started' : 'No ' . $status . ' listings'; ?>
            </p>
            <a href="creator-upload.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i>
                Create Listing
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/footer.php'; ?>
