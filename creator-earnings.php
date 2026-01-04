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

// Get earnings data
$earningsQuery = "SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM creator_purchases WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id)) as total_earnings,
    (SELECT COALESCE(SUM(amount), 0) FROM creator_purchases WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id) AND MONTH(purchased_at) = MONTH(CURRENT_DATE())) as this_month,
    (SELECT COALESCE(SUM(amount), 0) FROM creator_purchases WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id) AND MONTH(purchased_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))) as last_month,
    (SELECT COUNT(*) FROM creator_purchases WHERE listing_id IN (SELECT id FROM creator_listings WHERE creator_id = :user_id)) as total_transactions";

$stmt = $db->prepare($earningsQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$earnings = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate platform fee (15%)
$platformFee = $earnings['total_earnings'] * 0.15;
$yourEarnings = $earnings['total_earnings'] * 0.85;

// Get transactions
$transactionsQuery = "SELECT cp.*, cl.title, cl.thumbnail, cl.price
                      FROM creator_purchases cp
                      LEFT JOIN creator_listings cl ON cp.listing_id = cl.id
                      WHERE cl.creator_id = :user_id
                      ORDER BY cp.purchased_at DESC
                      LIMIT 50";

$stmt = $db->prepare($transactionsQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

.earnings-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.earnings-container {
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
    background: var(--color-success-emphasis);
    color: white;
}

.btn-primary:hover {
    background: #1a7f37;
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

.earnings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.earnings-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.earnings-card:hover {
    border-color: var(--color-accent-emphasis);
    transform: translateY(-2px);
    box-shadow: 0 12px 32px var(--color-shadow);
}

.card-label {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    margin-bottom: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-value {
    font-size: 2.25rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin-bottom: 0.5rem;
}

.card-subtext {
    font-size: 0.8rem;
    color: var(--color-text-tertiary);
}

.earnings-highlight {
    background: linear-gradient(135deg, var(--color-done-emphasis), var(--color-accent-emphasis));
    border-color: transparent;
}

.earnings-highlight .card-label,
.earnings-highlight .card-value,
.earnings-highlight .card-subtext {
    color: white;
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

.transaction-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.transaction-thumb {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--color-border-default);
}

.transaction-info h4 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0 0 0.25rem 0;
}

.transaction-info p {
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

.status-completed {
    background: rgba(35, 134, 54, 0.15);
    color: var(--color-success-emphasis);
}
</style>

<div class="earnings-page">
    <div class="earnings-container">
        <div class="dashboard-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div>
                        <h1>Earnings</h1>
                        <p style="color: var(--color-text-secondary); margin: 0.25rem 0 0 0;">
                            Track your income and transactions
                        </p>
                    </div>
                </div>
                
                <button class="btn btn-primary">
                    <i class="bi bi-download"></i>
                    Request Payout
                </button>
            </div>
            
            <div class="nav-tabs">
                <a href="creator-dashboard.php" class="nav-tab">
                    <i class="bi bi-house-fill"></i> Overview
                </a>
                <a href="creator-listings.php" class="nav-tab">
                    <i class="bi bi-grid"></i> Listings
                </a>
                <a href="creator-analytics.php" class="nav-tab">
                    <i class="bi bi-graph-up"></i> Analytics
                </a>
                <a href="creator-earnings.php" class="nav-tab active">
                    <i class="bi bi-currency-dollar"></i> Earnings
                </a>
            </div>
        </div>

        <div class="earnings-grid">
            <div class="earnings-card earnings-highlight">
                <div class="card-label">
                    <i class="bi bi-wallet2"></i>
                    Your Earnings (85%)
                </div>
                <div class="card-value">$<?php echo number_format($yourEarnings, 2); ?></div>
                <div class="card-subtext">Available for payout</div>
            </div>

            <div class="earnings-card">
                <div class="card-label">
                    <i class="bi bi-graph-up-arrow"></i>
                    This Month
                </div>
                <div class="card-value" style="color: var(--color-success-emphasis);">
                    $<?php echo number_format($earnings['this_month'], 2); ?>
                </div>
                <div class="card-subtext">
                    <?php 
                    $change = $earnings['last_month'] > 0 ? (($earnings['this_month'] - $earnings['last_month']) / $earnings['last_month'] * 100) : 0;
                    ?>
                    <?php if($change > 0): ?>
                        <i class="bi bi-arrow-up"></i> +<?php echo number_format($change, 1); ?>% from last month
                    <?php elseif($change < 0): ?>
                        <i class="bi bi-arrow-down"></i> <?php echo number_format($change, 1); ?>% from last month
                    <?php else: ?>
                        No change from last month
                    <?php endif; ?>
                </div>
            </div>

            <div class="earnings-card">
                <div class="card-label">
                    <i class="bi bi-receipt"></i>
                    Total Transactions
                </div>
                <div class="card-value"><?php echo number_format($earnings['total_transactions']); ?></div>
                <div class="card-subtext">All time sales</div>
            </div>

            <div class="earnings-card">
                <div class="card-label">
                    <i class="bi bi-percent"></i>
                    Platform Fee
                </div>
                <div class="card-value" style="font-size: 1.75rem;">15%</div>
                <div class="card-subtext">
                    $<?php echo number_format($platformFee, 2); ?> total fees
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-clock-history"></i>
                    Transaction History
                </h2>
            </div>

            <?php if(count($transactions) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Amount</th>
                        <th>Your Share</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transactions as $transaction): ?>
                    <tr>
                        <td>
                            <div class="transaction-item">
                                <img src="<?php echo htmlspecialchars($transaction['thumbnail'] ?? '/assets/images/default-content.jpg'); ?>" 
                                     alt="" class="transaction-thumb">
                                <div class="transaction-info">
                                    <h4><?php echo htmlspecialchars($transaction['title']); ?></h4>
                                    <p>Transaction ID: #<?php echo $transaction['id']; ?></p>
                                </div>
                            </div>
                        </td>
                        <td style="color: var(--color-text-primary); font-weight: 600;">
                            $<?php echo number_format($transaction['amount'], 2); ?>
                        </td>
                        <td style="color: var(--color-success-emphasis); font-weight: 700;">
                            $<?php echo number_format($transaction['amount'] * 0.85, 2); ?>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($transaction['purchased_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-text-secondary);">
                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 1rem;"></i>
                <p>No transactions yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
