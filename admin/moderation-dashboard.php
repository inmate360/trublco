<?php
session_start();
require_once '../config/database.php';
require_once '../classes/ContentModerator.php';

// Session timeout check
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verify admin status
try {
    $query = "SELECT id, username, email, is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user || !$current_user['is_admin']) {
        header('Location: ../index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Admin verification error: " . $e->getMessage());
    die("Database error. Please try again later.");
}

$moderator = new ContentModerator($db);

// Handle admin actions
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $log_id = (int)$_POST['log_id'];
    $action = $_POST['admin_action'];
    $notes = trim($_POST['admin_notes'] ?? '');
    
    $query = "UPDATE ai_moderation_logs 
              SET admin_reviewed = TRUE, 
                  admin_user_id = :admin_id, 
                  admin_action = :action,
                  admin_notes = :notes,
                  reviewed_at = NOW()
              WHERE id = :log_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':admin_id' => $_SESSION['user_id'],
        ':action' => $action,
        ':notes' => $notes,
        ':log_id' => $log_id
    ]);
    
    header('Location: moderation-dashboard.php?reviewed=1');
    exit();
}

// Get stats and flagged content
$stats = $moderator->getStats(30);
$flaggedContent = $moderator->getFlaggedContent(50, 0);

// Get daily stats for chart
$dailyQuery = "SELECT DATE(created_at) as date, COUNT(*) as count,
               SUM(CASE WHEN risk_level = 'high' OR risk_level = 'critical' THEN 1 ELSE 0 END) as high_risk
               FROM ai_moderation_logs
               WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
               GROUP BY DATE(created_at)
               ORDER BY date ASC";
$dailyStmt = $db->query($dailyQuery);
$dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

// Get content type breakdown
$typeQuery = "SELECT content_type, COUNT(*) as count
              FROM ai_moderation_logs
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY content_type";
$typeStmt = $db->query($typeQuery);
$contentTypes = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

include '../views/header.php';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- Main Container -->
<div class="min-h-screen bg-gh-bg">

    <!-- Top Navigation Bar -->
    <div class="sticky top-0 z-40 border-b border-gh-border bg-gh-panel/95 backdrop-blur-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="dashboard.php" class="text-gh-muted hover:text-gh-fg transition-colors">
                        <i class="bi bi-arrow-left text-xl"></i>
                    </a>
                    
                    <!-- Logo Section -->
                    <div class="flex items-center gap-3">
                        <img src="../uploads/trubl_logo.jpeg" alt="Trubl" class="h-8 w-8 rounded-lg object-cover">
                        <div class="h-6 w-px bg-gh-border"></div>
                        <div>
                            <h1 class="text-xl font-bold text-white">AI Content Moderation</h1>
                            <p class="text-xs text-gh-muted">Powered by Perplexity Sonar Pro</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <?php if(isset($_GET['reviewed'])): ?>
                    <div class="rounded-lg bg-green-500/10 border border-green-500/30 px-3 py-2 text-sm text-green-400">
                        <i class="bi bi-check-circle-fill mr-2"></i>Review submitted successfully
                    </div>
                    <?php endif; ?>
                    
                    <a href="../transparency.php" target="_blank" class="rounded-lg border border-gh-border bg-gh-panel2 px-3 py-2 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
                        <i class="bi bi-eye mr-2"></i>Public View
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Perplexity Banner -->
        <div class="mb-8 rounded-xl border border-purple-500/30 bg-gradient-to-r from-purple-500/10 to-pink-500/10 p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-pink-600 to-purple-600">
                    <i class="bi bi-robot text-2xl text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-white mb-2">Powered by Perplexity Sonar Pro</h3>
                    <p class="text-sm text-gh-muted mb-3">
                        Real-time AI moderation scanning all user-generated content for safety and policy compliance. 
                        Advanced threat detection with human oversight for optimal accuracy.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-purple-500/10 border border-purple-500/30 px-3 py-1 text-xs font-semibold text-purple-400">
                            <i class="bi bi-lightning-charge-fill mr-1"></i>Real-time Scanning
                        </span>
                        <span class="rounded-full bg-purple-500/10 border border-purple-500/30 px-3 py-1 text-xs font-semibold text-purple-400">
                            <i class="bi bi-shield-check mr-1"></i>Multi-layer Protection
                        </span>
                        <span class="rounded-full bg-purple-500/10 border border-purple-500/30 px-3 py-1 text-xs font-semibold text-purple-400">
                            <i class="bi bi-people-fill mr-1"></i>Human Oversight
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <!-- Total Scans -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gh-muted">Total Scans</p>
                        <h3 class="mt-2 text-3xl font-bold text-white"><?php echo number_format($stats['total_scans']); ?></h3>
                        <p class="mt-1 text-xs text-gh-muted">Last 30 days</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/10">
                        <i class="bi bi-search text-xl text-blue-400"></i>
                    </div>
                </div>
            </div>

            <!-- High Risk -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gh-muted">High Risk</p>
                        <h3 class="mt-2 text-3xl font-bold text-red-400"><?php echo number_format($stats['high_risk']); ?></h3>
                        <p class="mt-1 text-xs text-gh-muted">
                            <?php echo $stats['total_scans'] > 0 ? number_format(($stats['high_risk'] / $stats['total_scans']) * 100, 1) : 0; ?>% of total
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-500/10">
                        <i class="bi bi-exclamation-triangle-fill text-xl text-red-400"></i>
                    </div>
                </div>
            </div>

            <!-- Pending Review -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gh-muted">Pending Review</p>
                        <h3 class="mt-2 text-3xl font-bold text-orange-400"><?php echo number_format($stats['pending_review']); ?></h3>
                        <p class="mt-1 text-xs text-gh-muted">Awaiting action</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-500/10">
                        <i class="bi bi-clock-history text-xl text-orange-400"></i>
                    </div>
                </div>
            </div>

            <!-- Blocked -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gh-muted">Blocked</p>
                        <h3 class="mt-2 text-3xl font-bold text-purple-400"><?php echo number_format($stats['blocked']); ?></h3>
                        <p class="mt-1 text-xs text-gh-muted">Auto-blocked</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-500/10">
                        <i class="bi bi-shield-x text-xl text-purple-400"></i>
                    </div>
                </div>
            </div>

            <!-- Avg Risk Score -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gh-muted">Avg Risk Score</p>
                        <h3 class="mt-2 text-3xl font-bold text-white"><?php echo number_format($stats['avg_risk_score'], 1); ?></h3>
                        <p class="mt-1 text-xs text-gh-muted">Out of 100</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-500/10">
                        <i class="bi bi-graph-up text-xl text-green-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Types Scanned -->
        <div class="rounded-xl border border-gh-border bg-gh-panel overflow-hidden mb-8">
            <div class="border-b border-gh-border bg-gh-panel2 px-6 py-4">
                <h2 class="text-lg font-bold text-white">Content Types Being Scanned</h2>
                <p class="text-sm text-gh-muted">Real-time AI moderation across all platform content</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                                <i class="bi bi-tag-fill text-lg text-blue-400"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white mb-1">Marketplace Listings</h3>
                                <p class="text-xs text-gh-muted">Titles, descriptions, and user details scanned for scams, prohibited items, and inappropriate content</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-500/10">
                                <i class="bi bi-chat-dots-fill text-lg text-green-400"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white mb-1">Forum Discussions</h3>
                                <p class="text-xs text-gh-muted">Threads and replies checked for harassment, hate speech, spam, and off-topic content</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-purple-500/10">
                                <i class="bi bi-envelope-fill text-lg text-purple-400"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white mb-1">Private Messages</h3>
                                <p class="text-xs text-gh-muted">Romance scam detection, platform switching attempts, and inappropriate solicitation prevention</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-pink-500/10">
                                <i class="bi bi-book-fill text-lg text-pink-400"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white mb-1">User Stories</h3>
                                <p class="text-xs text-gh-muted">Content moderation for explicit material, personal info exposure, and community guideline violations</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Daily Activity Chart -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
                <h3 class="text-lg font-bold text-white mb-4">Daily Moderation Activity (14 Days)</h3>
                <div id="dailyChart"></div>
            </div>

            <!-- Content Type Distribution -->
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
                <h3 class="text-lg font-bold text-white mb-4">Content Type Distribution</h3>
                <div id="contentTypeChart"></div>
            </div>
        </div>

        <!-- Flagged Content Table -->
        <div class="rounded-xl border border-gh-border bg-gh-panel overflow-hidden">
            <div class="border-b border-gh-border bg-gh-panel2 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white">Flagged Content Requiring Review</h2>
                        <p class="text-sm text-gh-muted">Content flagged by AI for human review</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-gh-accent/10 px-3 py-1 text-sm font-semibold text-gh-accent">
                            <?php echo count($flaggedContent); ?> items
                        </span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-gh-border bg-gh-panel2">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">User</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Risk</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Categories</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">AI Reasoning</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gh-border">
                        <?php if(empty($flaggedContent)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-check-circle text-5xl text-green-500/20 mb-4"></i>
                                    <p class="text-lg font-semibold text-white">All Clear!</p>
                                    <p class="text-sm text-gh-muted mt-1">No flagged content requires review</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($flaggedContent as $item): ?>
                        <tr class="transition-colors hover:bg-gh-panel2">
                            <td class="px-6 py-4 text-sm text-gh-fg">
                                <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                <br>
                                <span class="text-xs text-gh-muted"><?php echo date('h:i A', strtotime($item['created_at'])); ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-gh-panel2 border border-gh-border px-2.5 py-0.5 text-xs font-medium text-gh-fg">
                                    <?php echo htmlspecialchars($item['content_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="../profile.php?id=<?php echo $item['user_id']; ?>" class="text-gh-accent hover:underline font-medium">
                                    <?php echo htmlspecialchars($item['username']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php 
                                $colors = [
                                    'low' => 'bg-green-500/10 border-green-500/30 text-green-400',
                                    'medium' => 'bg-yellow-500/10 border-yellow-500/30 text-yellow-400',
                                    'high' => 'bg-red-500/10 border-red-500/30 text-red-400',
                                    'critical' => 'bg-red-500/20 border-red-500/50 text-red-300'
                                ];
                                $color = $colors[$item['risk_level']] ?? 'bg-gray-500/10 border-gray-500/30 text-gray-400';
                                ?>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold <?php echo $color; ?>">
                                    <?php echo strtoupper($item['risk_level']); ?>
                                </span>
                                <span class="ml-2 text-xs text-gh-muted">(<?php echo $item['risk_score']; ?>)</span>
                            </td>
                            <td class="px-6 py-4 text-sm max-w-xs">
                                <?php 
                                $categories = json_decode($item['categories_flagged'], true);
                                if($categories && is_array($categories)) {
                                    foreach($categories as $cat) {
                                        echo '<span class="inline-flex items-center rounded-full bg-orange-500/10 border border-orange-500/30 px-2 py-0.5 text-xs font-medium text-orange-400 mr-1 mb-1">' 
                                             . htmlspecialchars($cat) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-gh-muted text-xs">None</span>';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gh-muted max-w-md">
                                <div class="line-clamp-2">
                                    <?php echo htmlspecialchars($item['ai_reasoning']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <button onclick="reviewContent(<?php echo $item['id']; ?>, '<?php echo $item['content_type']; ?>', <?php echo $item['content_id']; ?>)" 
                                        class="inline-flex items-center rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-3 py-1.5 text-xs font-semibold text-white transition-all hover:brightness-110">
                                    <i class="bi bi-eye mr-1"></i>
                                    Review
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm">
    <div class="mx-4 w-full max-w-3xl rounded-xl border border-gh-border bg-gh-panel shadow-2xl">
        <div class="border-b border-gh-border bg-gh-panel2 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">Review Flagged Content</h3>
                <button onclick="closeModal()" class="text-gh-muted hover:text-white transition-colors">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
        </div>
        
        <div id="reviewContent" class="max-h-96 overflow-y-auto border-b border-gh-border bg-gh-bg p-6"></div>
        
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="log_id" id="review_log_id">
            
            <div class="mb-4">
                <label class="mb-2 block text-sm font-semibold text-white">Admin Action</label>
                <select name="admin_action" class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg focus:border-gh-accent focus:outline-none" required>
                    <option value="approve">✓ Approve - False Positive</option>
                    <option value="warn">⚠ Warn User</option>
                    <option value="delete">🗑 Delete Content</option>
                    <option value="suspend">⏸ Suspend User (7 days)</option>
                    <option value="ban_user">🚫 Ban User Permanently</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="mb-2 block text-sm font-semibold text-white">Admin Notes</label>
                <textarea name="admin_notes" rows="3" class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted focus:border-gh-accent focus:outline-none" placeholder="Add internal notes about this decision..."></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
                    <i class="bi bi-check-circle mr-2"></i>Submit Decision
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ApexCharts - Daily Activity Line Chart
var dailyChartOptions = {
    series: [{
        name: 'Total Scans',
        data: [<?php foreach($dailyData as $d) echo $d['count'] . ','; ?>]
    }, {
        name: 'High Risk',
        data: [<?php foreach($dailyData as $d) echo $d['high_risk'] . ','; ?>]
    }],
    chart: {
        type: 'area',
        height: 300,
        background: 'transparent',
        toolbar: { show: true },
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800
        }
    },
    colors: ['#3b82f6', '#ef4444'],
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    fill: {
        type: 'gradient',
        gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.1
        }
    },
    xaxis: {
        categories: [<?php foreach($dailyData as $d) echo "'" . date('M j', strtotime($d['date'])) . "',"; ?>],
        labels: {
            style: {
                colors: '#9ca3af',
                fontSize: '12px'
            }
        }
    },
    yaxis: {
        labels: {
            style: {
                colors: '#9ca3af',
                fontSize: '12px'
            }
        }
    },
    grid: {
        borderColor: '#374151',
        strokeDashArray: 3
    },
    legend: {
        position: 'top',
        labels: {
            colors: '#9ca3af'
        }
    },
    tooltip: {
        theme: 'dark',
        x: {
            format: 'MMM dd'
        }
    }
};

var dailyChart = new ApexCharts(document.querySelector("#dailyChart"), dailyChartOptions);
dailyChart.render();

// ApexCharts - Content Type Donut Chart
var contentTypeOptions = {
    series: [<?php foreach($contentTypes as $t) echo $t['count'] . ','; ?>],
    chart: {
        type: 'donut',
        height: 300,
        background: 'transparent',
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800
        }
    },
    labels: [<?php foreach($contentTypes as $t) echo "'" . ucfirst(str_replace('_', ' ', $t['content_type'])) . "',"; ?>],
    colors: ['#3b82f6', '#10b981', '#a855f7', '#ec4899', '#fb923c'],
    legend: {
        position: 'right',
        labels: {
            colors: '#9ca3af'
        }
    },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        color: '#ffffff',
                        fontSize: '16px',
                        fontWeight: 600
                    }
                }
            }
        }
    },
    dataLabels: {
        enabled: true,
        style: {
            fontSize: '12px',
            colors: ['#ffffff']
        },
        dropShadow: {
            enabled: false
        }
    },
    tooltip: {
        theme: 'dark'
    }
};

var contentTypeChart = new ApexCharts(document.querySelector("#contentTypeChart"), contentTypeOptions);
contentTypeChart.render();

function reviewContent(logId, contentType, contentId) {
    document.getElementById('review_log_id').value = logId;
    document.getElementById('reviewModal').classList.remove('hidden');
    document.getElementById('reviewModal').classList.add('flex');
    
    document.getElementById('reviewContent').innerHTML = '<div class="text-center py-8"><i class="bi bi-hourglass-split text-3xl text-gh-muted animate-pulse"></i><p class="text-gh-muted mt-2">Loading content...</p></div>';
    
    setTimeout(() => {
        document.getElementById('reviewContent').innerHTML = `
            <div class="space-y-4">
                <div class="rounded-lg border border-gh-border bg-gh-panel p-4">
                    <p class="text-xs font-semibold uppercase text-gh-muted mb-2">Content Type</p>
                    <p class="text-sm text-gh-fg">${contentType}</p>
                </div>
                <div class="rounded-lg border border-gh-border bg-gh-panel p-4">
                    <p class="text-xs font-semibold uppercase text-gh-muted mb-2">Content ID</p>
                    <p class="text-sm text-gh-fg">#${contentId}</p>
                </div>
                <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-4">
                    <p class="text-xs font-semibold uppercase text-yellow-400 mb-2">⚠ Note</p>
                    <p class="text-sm text-gh-muted">Implement content fetching via AJAX to display full content here</p>
                </div>
            </div>
        `;
    }, 500);
}

function closeModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.getElementById('reviewModal').classList.remove('flex');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include '../views/footer.php'; ?>
