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

// Get time period
$period = $_GET['period'] ?? '30days';

// Calculate date range
$dateCondition = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
switch($period) {
    case '7days':
        $dateCondition = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '90days':
        $dateCondition = "DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'year':
        $dateCondition = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
}

// Get analytics data
$analyticsQuery = "SELECT 
    DATE(created_at) as date,
    SUM(views) as views,
    COUNT(*) as listings
    FROM creator_listings 
    WHERE creator_id = :user_id 
    AND created_at >= $dateCondition
    GROUP BY DATE(created_at)
    ORDER BY date DESC";

$stmt = $db->prepare($analyticsQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$analyticsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sales data
$salesQuery = "SELECT 
    DATE(cp.purchased_at) as date,
    COUNT(*) as sales,
    SUM(cp.amount) as revenue
    FROM creator_purchases cp
    LEFT JOIN creator_listings cl ON cp.listing_id = cl.id
    WHERE cl.creator_id = :user_id 
    AND cp.purchased_at >= $dateCondition
    GROUP BY DATE(cp.purchased_at)
    ORDER BY date DESC";

$stmt = $db->prepare($salesQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performing listings
$topListingsQuery = "SELECT 
    cl.id,
    cl.title,
    cl.thumbnail,
    cl.price,
    cl.views,
    COUNT(cp.id) as sales,
    SUM(cp.amount) as revenue
    FROM creator_listings cl
    LEFT JOIN creator_purchases cp ON cl.id = cp.listing_id
    WHERE cl.creator_id = :user_id
    GROUP BY cl.id
    ORDER BY revenue DESC
    LIMIT 5";

$stmt = $db->prepare($topListingsQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$topListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category breakdown
$categoryQuery = "SELECT 
    category,
    COUNT(*) as count,
    SUM(views) as total_views
    FROM creator_listings 
    WHERE creator_id = :user_id AND status = 'active'
    GROUP BY category
    ORDER BY count DESC";

$stmt = $db->prepare($categoryQuery);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

.analytics-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.analytics-container {
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

.period-selector {
    display: flex;
    gap: 0.5rem;
    background: var(--color-canvas-overlay);
    padding: 0.5rem;
    border-radius: 8px;
}

.period-btn {
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    color: var(--color-text-secondary);
    cursor: pointer;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
}

.period-btn:hover {
    color: var(--color-text-primary);
    background: var(--color-canvas-subtle);
}

.period-btn.active {
    background: var(--color-accent-emphasis);
    color: white;
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

.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.chart-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--color-text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-accent-emphasis);
}

.chart-container {
    position: relative;
    height: 300px;
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

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--color-canvas-overlay);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--color-accent-emphasis);
    border-radius: 4px;
    transition: width 0.3s;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.metric-item {
    background: var(--color-canvas-overlay);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--color-border-muted);
}

.metric-label {
    font-size: 0.8rem;
    color: var(--color-text-secondary);
    margin-bottom: 0.5rem;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text-primary);
}

@media (max-width: 768px) {
    .chart-grid {
        grid-template-columns: 1fr;
    }
    
    .period-selector {
        width: 100%;
        overflow-x: auto;
    }
}
</style>

<div class="analytics-page">
    <div class="analytics-container">
        <div class="dashboard-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <h1>Analytics</h1>
                        <p style="color: var(--color-text-secondary); margin: 0.25rem 0 0 0;">
                            Track your performance and insights
                        </p>
                    </div>
                </div>
                
                <div class="period-selector">
                    <a href="?period=7days" class="period-btn <?php echo $period == '7days' ? 'active' : ''; ?>">7 Days</a>
                    <a href="?period=30days" class="period-btn <?php echo $period == '30days' ? 'active' : ''; ?>">30 Days</a>
                    <a href="?period=90days" class="period-btn <?php echo $period == '90days' ? 'active' : ''; ?>">90 Days</a>
                    <a href="?period=year" class="period-btn <?php echo $period == 'year' ? 'active' : ''; ?>">1 Year</a>
                </div>
            </div>
            
            <div class="nav-tabs">
                <a href="creator-dashboard.php" class="nav-tab">
                    <i class="bi bi-house-fill"></i> Overview
                </a>
                <a href="creator-listings.php" class="nav-tab">
                    <i class="bi bi-grid"></i> Listings
                </a>
                <a href="creator-analytics.php" class="nav-tab active">
                    <i class="bi bi-graph-up"></i> Analytics
                </a>
                <a href="creator-earnings.php" class="nav-tab">
                    <i class="bi bi-currency-dollar"></i> Earnings
                </a>
            </div>
        </div>

        <!-- Charts -->
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="bi bi-eye"></i>
                        Views Over Time
                    </h3>
                    <span class="chart-value">
                        <?php echo number_format(array_sum(array_column($analyticsData, 'views'))); ?>
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="viewsChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="bi bi-currency-dollar"></i>
                        Revenue Over Time
                    </h3>
                    <span class="chart-value">
                        $<?php echo number_format(array_sum(array_column($salesData, 'revenue')), 2); ?>
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Performing Listings -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-trophy"></i>
                    Top Performing Content
                </h2>
            </div>

            <?php if(count($topListings) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Views</th>
                        <th>Sales</th>
                        <th>Revenue</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxRevenue = max(array_column($topListings, 'revenue'));
                    foreach($topListings as $listing): 
                    ?>
                    <tr>
                        <td>
                            <div class="listing-item">
                                <img src="<?php echo htmlspecialchars($listing['thumbnail'] ?? '/assets/images/default-content.jpg'); ?>" 
                                     alt="" class="listing-thumb">
                                <div class="listing-info">
                                    <h4><?php echo htmlspecialchars($listing['title']); ?></h4>
                                    <p>$<?php echo number_format($listing['price'], 2); ?></p>
                                </div>
                            </div>
                        </td>
                        <td><?php echo number_format($listing['views']); ?></td>
                        <td style="color: var(--color-text-primary); font-weight: 600;">
                            <?php echo number_format($listing['sales']); ?>
                        </td>
                        <td style="color: var(--color-success-emphasis); font-weight: 700;">
                            $<?php echo number_format($listing['revenue'], 2); ?>
                        </td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" 
                                     style="width: <?php echo $maxRevenue > 0 ? ($listing['revenue'] / $maxRevenue * 100) : 0; ?>%">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-text-secondary);">
                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 1rem;"></i>
                <p>No performance data available yet</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Category Breakdown -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-pie-chart"></i>
                    Category Breakdown
                </h2>
            </div>

            <div class="metric-grid">
                <?php foreach($categoryData as $cat): ?>
                <div class="metric-item">
                    <div class="metric-label"><?php echo htmlspecialchars(ucfirst($cat['category'] ?? 'Uncategorized')); ?></div>
                    <div class="metric-value"><?php echo number_format($cat['count']); ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-text-tertiary); margin-top: 0.25rem;">
                        <?php echo number_format($cat['total_views']); ?> views
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Views Chart
const viewsCtx = document.getElementById('viewsChart').getContext('2d');
const viewsChart = new Chart(viewsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_reverse(array_column($analyticsData, 'date'))); ?>,
        datasets: [{
            label: 'Views',
            data: <?php echo json_encode(array_reverse(array_column($analyticsData, 'views'))); ?>,
            borderColor: '#1f6feb',
            backgroundColor: 'rgba(31, 111, 235, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#30363d'
                },
                ticks: {
                    color: '#7d8590'
                }
            },
            x: {
                grid: {
                    color: '#30363d'
                },
                ticks: {
                    color: '#7d8590'
                }
            }
        }
    }
});

// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_reverse(array_column($salesData, 'date'))); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_reverse(array_column($salesData, 'revenue'))); ?>,
            backgroundColor: '#8957e5',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#30363d'
                },
                ticks: {
                    color: '#7d8590',
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#7d8590'
                }
            }
        }
    }
});
</script>

<?php include 'views/footer.php'; ?>
