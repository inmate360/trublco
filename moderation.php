<?php
// moderation.php - Admin Content Moderation Dashboard
session_start();
require_once 'config/database.php';
require_once 'config/moderation-config.php';
require_once 'includes/PerplexityModerationService.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$moderator = new PerplexityModerationService($db, PERPLEXITY_API_KEY);
$currentUser = $_SESSION['user_id'];

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $queueId = (int)$_POST['queue_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Approve content
        $stmt = $db->prepare("
            UPDATE moderation_queue 
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $currentUser, $queueId);
        $stmt->execute();
        
        // Get content details and update original content
        $item = $db->query("SELECT content_type, content_id FROM moderation_queue WHERE id = $queueId")->fetch_assoc();
        if ($item) {
            $table = $item['content_type'] . 's'; // listings, stories, etc.
            $db->query("UPDATE {$table} SET status = 'active' WHERE id = " . $item['content_id']);
        }
        
        $_SESSION['success'] = "Content approved successfully";
        
    } elseif ($action === 'reject') {
        // Reject content
        $stmt = $db->prepare("
            UPDATE moderation_queue 
            SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $currentUser, $queueId);
        $stmt->execute();
        
        // Update original content
        $item = $db->query("SELECT content_type, content_id FROM moderation_queue WHERE id = $queueId")->fetch_assoc();
        if ($item) {
            $table = $item['content_type'] . 's';
            $db->query("UPDATE {$table} SET status = 'rejected' WHERE id = " . $item['content_id']);
        }
        
        $_SESSION['success'] = "Content rejected successfully";
        
    } elseif ($action === 'bulk_approve') {
        // Bulk approve
        $ids = $_POST['selected_ids'] ?? [];
        if (!empty($ids)) {
            $idList = implode(',', array_map('intval', $ids));
            $db->query("
                UPDATE moderation_queue 
                SET status = 'approved', reviewed_by = {$currentUser}, reviewed_at = NOW()
                WHERE id IN ({$idList})
            ");
            $_SESSION['success'] = count($ids) . " items approved";
        }
    }
    
    header('Location: moderation.php?status=' . ($_GET['status'] ?? 'flagged'));
    exit;
}

// Get filter parameters
$filter = $_GET['status'] ?? 'flagged';
$contentTypeFilter = $_GET['type'] ?? null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];
$types = '';

if (in_array($filter, ['pending', 'flagged', 'approved', 'rejected'])) {
    $whereConditions[] = "mq.status = ?";
    $params[] = $filter;
    $types .= 's';
}

if ($contentTypeFilter) {
    $whereConditions[] = "mq.content_type = ?";
    $params[] = $contentTypeFilter;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM moderation_queue mq {$whereClause}";
if (!empty($params)) {
    $stmt = $db->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalItems = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $totalItems = $db->query($countQuery)->fetch_assoc()['total'];
}

$totalPages = ceil($totalItems / $perPage);

// Get queue items
$query = "
    SELECT mq.*, u.username, u.email 
    FROM moderation_queue mq
    LEFT JOIN users u ON mq.user_id = u.id
    {$whereClause}
    ORDER BY mq.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";

if (!empty($params)) {
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $queue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $queue = $db->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN auto_action = 1 THEN 1 ELSE 0 END) as auto_actions
    FROM moderation_queue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Moderation - Basehit Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'gh-bg': '#0a0a0f',
                        'gh-panel': '#1a1a2e',
                        'gh-panel2': '#16213e',
                        'gh-border': '#2a2a3e',
                        'gh-accent': '#e94560',
                        'gh-success': '#00d9a3',
                        'gh-muted': '#9ca3af',
                        'gh-fg': '#f5f5f7'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gh-bg text-gh-fg">
    
    <!-- Header -->
    <header class="border-b border-gh-border bg-gh-panel/50 backdrop-blur sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="index.php" class="text-2xl font-bold bg-gradient-to-r from-gh-accent to-purple-500 bg-clip-text text-transparent">
                        Basehit
                    </a>
                    <nav class="hidden md:flex items-center gap-6">
                        <a href="admin-dashboard.php" class="text-gh-muted hover:text-gh-fg transition">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a href="moderation.php" class="text-gh-fg font-semibold">
                            <i class="bi bi-shield-check"></i> Moderation
                        </a>
                        <a href="flagged-content.php" class="text-gh-muted hover:text-gh-fg transition">
                            <i class="bi bi-eye"></i> Public View
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gh-muted">Admin: <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                    <a href="logout.php" class="px-4 py-2 bg-gh-panel border border-gh-border rounded-lg hover:bg-gh-panel2 transition text-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <!-- Page Title -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold mb-2 flex items-center gap-3">
                <i class="bi bi-shield-check text-gh-accent"></i>
                Content Moderation
            </h1>
            <p class="text-gh-muted">AI-powered content safety dashboard with Perplexity Sonar Pro</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-gh-success/20 border border-gh-success/30 rounded-lg p-4 mb-6">
                <i class="bi bi-check-circle text-gh-success"></i>
                <span class="text-gh-success"><?= htmlspecialchars($_SESSION['success']) ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <div class="bg-gh-panel border border-gh-border rounded-xl p-4">
                <div class="text-2xl font-bold text-gh-fg"><?= number_format($stats['total']) ?></div>
                <div class="text-sm text-gh-muted">Total (7d)</div>
            </div>
            <div class="bg-gh-panel border border-gh-border rounded-xl p-4">
                <div class="text-2xl font-bold text-gh-success"><?= number_format($stats['approved']) ?></div>
                <div class="text-sm text-gh-muted">Approved</div>
            </div>
            <div class="bg-gh-panel border border-gh-border rounded-xl p-4">
                <div class="text-2xl font-bold text-red-500"><?= number_format($stats['rejected']) ?></div>
                <div class="text-sm text-gh-muted">Rejected</div>
            </div>
            <div class="bg-gh-panel border border-gh-border rounded-xl p-4">
                <div class="text-2xl font-bold text-yellow-500"><?= number_format($stats['flagged']) ?></div>
                <div class="text-sm text-gh-muted">Flagged</div>
            </div>
            <div class="bg-gh-panel border border-gh-border rounded-xl p-4">
                <div class="text-2xl font-bold text-blue-500"><?= number_format($stats['pending']) ?></div>
                <div class="text-sm text-gh-muted">Pending</div>
            </div>
            <div class="bg-gh-panel border border-gh-border rounded-xl p-4">
                <div class="text-2xl font-bold text-purple-500"><?= number_format($stats['auto_actions']) ?></div>
                <div class="text-sm text-gh-muted">Auto</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-gh-panel border border-gh-border rounded-xl p-6 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="text-sm text-gh-muted mb-2 block">Status</label>
                    <div class="flex gap-2">
                        <a href="?status=flagged<?= $contentTypeFilter ? '&type='.$contentTypeFilter : '' ?>" 
                           class="px-4 py-2 rounded-lg <?= $filter === 'flagged' ? 'bg-gh-accent text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border' ?> transition text-sm">
                            Flagged
                        </a>
                        <a href="?status=pending<?= $contentTypeFilter ? '&type='.$contentTypeFilter : '' ?>" 
                           class="px-4 py-2 rounded-lg <?= $filter === 'pending' ? 'bg-gh-accent text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border' ?> transition text-sm">
                            Pending
                        </a>
                        <a href="?status=approved<?= $contentTypeFilter ? '&type='.$contentTypeFilter : '' ?>" 
                           class="px-4 py-2 rounded-lg <?= $filter === 'approved' ? 'bg-gh-accent text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border' ?> transition text-sm">
                            Approved
                        </a>
                        <a href="?status=rejected<?= $contentTypeFilter ? '&type='.$contentTypeFilter : '' ?>" 
                           class="px-4 py-2 rounded-lg <?= $filter === 'rejected' ? 'bg-gh-accent text-white' : 'bg-gh-panel2 text-gh-muted hover:bg-gh-border' ?> transition text-sm">
                            Rejected
                        </a>
                    </div>
                </div>
                
                <!-- Content Type Filter -->
                <div>
                    <label class="text-sm text-gh-muted mb-2 block">Content Type</label>
                    <select onchange="location.href='?status=<?= $filter ?>&type=' + this.value" 
                            class="px-4 py-2 bg-gh-panel2 border border-gh-border rounded-lg text-gh-fg text-sm">
                        <option value="">All Types</option>
                        <option value="listing" <?= $contentTypeFilter === 'listing' ? 'selected' : '' ?>>Listings</option>
                        <option value="story" <?= $contentTypeFilter === 'story' ? 'selected' : '' ?>>Stories</option>
                        <option value="forum_post" <?= $contentTypeFilter === 'forum_post' ? 'selected' : '' ?>>Forum Posts</option>
                        <option value="message" <?= $contentTypeFilter === 'message' ? 'selected' : '' ?>>Messages</option>
                        <option value="profile" <?= $contentTypeFilter === 'profile' ? 'selected' : '' ?>>Profiles</option>
                    </select>
                </div>
                
                <!-- Quick Stats -->
                <div class="ml-auto">
                    <div class="text-sm text-gh-muted">Showing <?= count($queue) ?> of <?= number_format($totalItems) ?> items</div>
                </div>
            </div>
        </div>
        
        <!-- Queue Items -->
        <form method="POST" id="bulkForm">
            <input type="hidden" name="action" id="bulkAction">
            
            <?php if (!empty($queue) && in_array($filter, ['flagged', 'pending'])): ?>
                <div class="mb-4 flex gap-3">
                    <button type="button" onclick="bulkApprove()" class="px-4 py-2 bg-gh-success text-white rounded-lg hover:bg-green-600 transition text-sm">
                        <i class="bi bi-check-circle"></i> Bulk Approve Selected
                    </button>
                    <button type="button" onclick="selectAll()" class="px-4 py-2 bg-gh-panel2 border border-gh-border rounded-lg hover:bg-gh-border transition text-sm">
                        <i class="bi bi-check-square"></i> Select All
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="space-y-4">
                <?php foreach ($queue as $item): 
                    $categories = json_decode($item['flagged_categories'], true) ?? [];
                    $scores = json_decode($item['moderation_score'], true) ?? [];
                    $statusColors = [
                        'rejected' => 'red',
                        'flagged' => 'yellow',
                        'approved' => 'green',
                        'pending' => 'blue'
                    ];
                    $color = $statusColors[$item['status']] ?? 'gray';
                ?>
                    <div class="bg-gh-panel border border-gh-border rounded-xl p-6 hover:border-gh-accent/50 transition">
                        <div class="flex items-start gap-4">
                            <?php if (in_array($item['status'], ['flagged', 'pending'])): ?>
                                <input type="checkbox" name="selected_ids[]" value="<?= $item['id'] ?>" 
                                       class="mt-1 w-5 h-5 rounded border-gh-border bg-gh-panel2 text-gh-accent">
                            <?php endif; ?>
                            
                            <div class="flex-1">
                                <!-- Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <span class="px-3 py-1 bg-<?= $color ?>-500/20 text-<?= $color ?>-400 border border-<?= $color ?>-500/30 rounded-full text-xs font-semibold uppercase">
                                            <?= $item['status'] ?>
                                        </span>
                                        <span class="px-3 py-1 bg-gh-panel2 border border-gh-border rounded-full text-xs text-gh-muted">
                                            <i class="bi bi-file-earmark"></i>
                                            <?= ucfirst(str_replace('_', ' ', $item['content_type'])) ?>
                                        </span>
                                        <?php if ($item['auto_action']): ?>
                                            <span class="px-3 py-1 bg-purple-500/20 text-purple-400 border border-purple-500/30 rounded-full text-xs">
                                                <i class="bi bi-cpu"></i> AI Auto
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gh-muted">
                                        <?= date('M j, Y g:i A', strtotime($item['created_at'])) ?>
                                    </span>
                                </div>
                                
                                <!-- User Info -->
                                <div class="mb-3 text-sm text-gh-muted">
                                    <i class="bi bi-person"></i>
                                    User: <span class="text-gh-fg"><?= htmlspecialchars($item['username'] ?? 'Unknown') ?></span>
                                    <?php if ($item['email']): ?>
                                        (<?= htmlspecialchars($item['email']) ?>)
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Content Preview -->
                                <div class="bg-gh-panel2 border border-gh-border rounded-lg p-4 mb-3">
                                    <p class="text-gh-fg text-sm">
                                        <?= nl2br(htmlspecialchars(substr($item['content_text'], 0, 300))) ?>
                                        <?= strlen($item['content_text']) > 300 ? '...' : '' ?>
                                    </p>
                                </div>
                                
                                <!-- Flagged Categories -->
                                <?php if (!empty($categories)): ?>
                                    <div class="mb-3">
                                        <div class="text-sm text-gh-muted mb-2">
                                            <i class="bi bi-exclamation-triangle"></i> Violations:
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($categories as $cat): ?>
                                                <span class="px-3 py-1 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full text-xs">
                                                    <?= htmlspecialchars(str_replace('_', ' ', $cat)) ?>
                                                    <?php if (isset($scores[$cat])): ?>
                                                        <span class="font-mono">(<?= round($scores[$cat] * 100, 1) ?>%)</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Confidence Score -->
                                <?php if ($item['confidence_score']): ?>
                                    <div class="flex items-center gap-3 mb-3">
                                        <span class="text-sm text-gh-muted">AI Confidence:</span>
                                        <div class="flex-1 max-w-xs bg-gh-bg border border-gh-border rounded-full h-2">
                                            <div class="bg-gh-accent rounded-full h-2 transition-all" 
                                                 style="width: <?= $item['confidence_score'] * 100 ?>%"></div>
                                        </div>
                                        <span class="text-sm font-mono text-gh-fg">
                                            <?= round($item['confidence_score'] * 100, 1) ?>%
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Actions -->
                                <?php if (in_array($item['status'], ['flagged', 'pending'])): ?>
                                    <div class="flex gap-3 pt-4 border-t border-gh-border">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="queue_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="px-6 py-2 bg-gh-success text-white rounded-lg hover:bg-green-600 transition text-sm font-semibold">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="queue_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm font-semibold">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                        <a href="<?= $item['content_type'] ?>.php?id=<?= $item['content_id'] ?>" target="_blank" 
                                           class="px-6 py-2 bg-gh-panel2 border border-gh-border rounded-lg hover:bg-gh-bg transition text-sm">
                                            <i class="bi bi-box-arrow-up-right"></i> View Full
                                        </a>
                                    </div>
                                <?php elseif ($item['reviewed_by']): ?>
                                    <div class="text-sm text-gh-muted pt-3 border-t border-gh-border">
                                        <i class="bi bi-person-check"></i>
                                        Reviewed by admin on <?= date('M j, Y g:i A', strtotime($item['reviewed_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($queue)): ?>
                    <div class="bg-gh-panel border border-gh-border rounded-xl p-12 text-center">
                        <i class="bi bi-inbox text-6xl text-gh-muted mb-4"></i>
                        <p class="text-gh-muted text-lg">No items in this queue</p>
                        <p class="text-sm text-gh-muted mt-2">Try changing filters or check back later</p>
                    </div>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center items-center gap-2 mt-8">
                <?php if ($page > 1): ?>
                    <a href="?status=<?= $filter ?><?= $contentTypeFilter ? '&type='.$contentTypeFilter : '' ?>&page=<?= $page - 1 ?>" 
                       class="px-4 py-2 bg-gh-panel border border-gh-border rounded-lg hover:bg-gh-panel2 transition">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <span class="px-4 py-2 text-gh-muted">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?status=<?= $filter ?><?= $contentTypeFilter ? '&type='.$contentTypeFilter : '' ?>&page=<?= $page + 1 ?>" 
                       class="px-4 py-2 bg-gh-panel border border-gh-border rounded-lg hover:bg-gh-panel2 transition">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
    
    <script>
        function selectAll() {
            document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = true);
        }
        
        function bulkApprove() {
            const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checked.length === 0) {
                alert('Please select items first');
                return;
            }
            
            if (confirm(`Approve ${checked.length} selected items?`)) {
                document.getElementById('bulkAction').value = 'bulk_approve';
                document.getElementById('bulkForm').submit();
            }
        }
    </script>
    
</body>
</html>
