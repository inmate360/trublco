<?php
session_start();
require_once '../config/database.php';
require_once '../classes/ContentModerator.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session timeout check (30 minutes)
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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $logId = $_POST['log_id'] ?? 0;
    $action = $_POST['action'];
    $notes = $_POST['admin_notes'] ?? '';

    $moderator->markAsReviewed($logId, $_SESSION['user_id'], $action, $notes);

    // Handle additional actions
    if ($action === 'banned_user' && isset($_POST['user_id'])) {
        $stmt = $db->prepare("UPDATE users SET status = 'banned' WHERE id = :id");
        $stmt->execute(['id' => $_POST['user_id']]);
    } elseif ($action === 'rejected' && isset($_POST['content_type']) && isset($_POST['content_id'])) {
        $contentType = $_POST['content_type'];
        $contentId = $_POST['content_id'];
        if ($contentType === 'listing') {
            $stmt = $db->prepare("UPDATE listings SET status = 'rejected', is_deleted = 1 WHERE id = :id");
            $stmt->execute(['id' => $contentId]);
        }
    } elseif ($action === 'approved' && isset($_POST['content_type']) && isset($_POST['content_id'])) {
        $contentType = $_POST['content_type'];
        $contentId = $_POST['content_id'];
        if ($contentType === 'listing') {
            $stmt = $db->prepare("UPDATE listings SET status = 'active', moderation_status = 'approved' WHERE id = :id");
            $stmt->execute(['id' => $contentId]);
        }
    }
    header('Location: moderation.php?success=1');
    exit;
}

// Get stats
$mod_stats = $moderator->getStats();

// Get flagged content
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$flaggedContent = $moderator->getFlaggedContent($perPage, $offset);

// Get total flagged count for pagination
$countQuery = "SELECT COUNT(*) FROM moderation_logs WHERE risk_level IN ('high', 'medium') AND reviewed_at IS NULL";
$totalFlagged = $db->query($countQuery)->fetchColumn();
$totalPages = ceil($totalFlagged / $perPage);

// Get stats for sidebar badges
$stats = [];
try {
    $stmt = $db->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'pending'");
    $stats['pending_verifications'] = $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM user_subscriptions WHERE status = 'pending'");
    $stats['pending_upgrades'] = $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $stats['pending_reports'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $stats = ['pending_verifications' => 0, 'pending_upgrades' => 0, 'pending_reports' => 0];
}

$current_page = 'moderation';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Moderation - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0">
        <div class="h-full px-3 py-4 overflow-y-auto bg-gray-800 border-r border-gray-700">
            <div class="mb-5 px-3 py-2">
                <a href="../index.php" class="flex items-center">
                    <span class="self-center text-2xl font-bold whitespace-nowrap gradient-bg bg-clip-text text-transparent">⚡ trubl</span>
                </a>
                <p class="mt-1 text-xs text-gray-400">Admin Control Panel</p>
            </div>

            <div class="mb-4 p-4 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-blue-600 font-bold">
                        <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-white text-sm font-semibold"><?php echo htmlspecialchars($current_user['username']); ?></p>
                        <p class="text-xs text-blue-100">Administrator</p>
                    </div>
                </div>
            </div>

            <ul class="space-y-2 font-medium">
                <li><a href="dashboard.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group"><i class="fas fa-tachometer-alt w-5"></i><span class="ml-3">Dashboard</span></a></li>
                <li><a href="users.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group"><i class="fas fa-users w-5"></i><span class="ml-3">Users</span></a></li>
                <li><a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group"><i class="fas fa-list w-5"></i><span class="ml-3">Listings</span></a></li>
                <li><a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group relative"><i class="fas fa-shield-alt w-5"></i><span class="ml-3">Verifications</span><?php if($stats['pending_verifications']>0): ?><span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $stats['pending_verifications']; ?></span><?php endif; ?></a></li>
                <li><a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group relative"><i class="fas fa-gem w-5"></i><span class="ml-3">Upgrades</span><?php if($stats['pending_upgrades']>0): ?><span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $stats['pending_upgrades']; ?></span><?php endif; ?></a></li>
                <li><a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group relative"><i class="fas fa-flag w-5"></i><span class="ml-3">Reports</span><?php if($stats['pending_reports']>0): ?><span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $stats['pending_reports']; ?></span><?php endif; ?></a></li>
                <li><a href="mod.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg group"><i class="fas fa-shield-virus w-5"></i><span class="ml-3">Content Moderation</span></a></li>
                <li><a href="news.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group"><i class="fas fa-bullhorn w-5"></i><span class="ml-3">News & Announcements</span></a></li>
            </ul>

            <div class="pt-4 mt-4 border-t border-gray-700 space-y-2">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 hover:bg-gray-700 rounded-lg"><i class="fas fa-home w-5"></i><span class="ml-3">Back to Site</span></a>
                <a href="../logout.php" class="flex items-center p-3 text-red-400 hover:bg-red-900/20 rounded-lg"><i class="fas fa-sign-out-alt w-5"></i><span class="ml-3">Logout</span></a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="sm:ml-64">
        <nav class="bg-gray-800 border-b border-gray-700 px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <button data-drawer-target="sidebar" data-drawer-toggle="sidebar" class="p-2 text-gray-400 sm:hidden"><i class="fas fa-bars"></i></button>
                    <div class="ml-3">
                        <h1 class="text-xl font-bold text-white">AI Moderation</h1>
                        <p class="text-xs text-gray-400">Review flagged content</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button type="button" class="flex text-sm bg-gray-700 rounded-full focus:ring-4 focus:ring-gray-600" data-dropdown-toggle="dropdown-user">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                    </button>
                    <div class="z-50 hidden my-4 text-base list-none bg-gray-700 divide-y divide-gray-600 rounded-lg shadow" id="dropdown-user">
                        <div class="px-4 py-3"><p class="text-sm text-white"><?php echo htmlspecialchars($current_user['username']); ?></p></div>
                        <ul class="py-2">
                            <li><a href="../profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">Profile</a></li>
                            <li><a href="settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">Settings</a></li>
                            <li><a href="../logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-600">Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <main class="p-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="p-6 bg-blue-600/20 border border-blue-500/30 rounded-xl">
                    <div class="text-sm font-bold text-blue-400 uppercase">Total Scans</div>
                    <div class="text-3xl font-bold text-white"><?php echo number_format($mod_stats['total_scans']); ?></div>
                </div>
                <div class="p-6 bg-red-600/20 border border-red-500/30 rounded-xl">
                    <div class="text-sm font-bold text-red-400 uppercase">High Risk</div>
                    <div class="text-3xl font-bold text-white"><?php echo number_format($mod_stats['high_risk']); ?></div>
                </div>
                <div class="p-6 bg-yellow-600/20 border border-yellow-500/30 rounded-xl">
                    <div class="text-sm font-bold text-yellow-400 uppercase">Medium Risk</div>
                    <div class="text-3xl font-bold text-white"><?php echo number_format($mod_stats['medium_risk']); ?></div>
                </div>
                <div class="p-6 bg-purple-600/20 border border-purple-500/30 rounded-xl">
                    <div class="text-sm font-bold text-purple-400 uppercase">Pending</div>
                    <div class="text-3xl font-bold text-white"><?php echo number_format($mod_stats['pending']); ?></div>
                </div>
            </div>

            <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-700"><h3 class="text-xl font-bold text-white">Flagged Content</h3></div>
                <div class="p-6">
                    <?php if (empty($flaggedContent)): ?>
                        <div class="text-center py-12 text-gray-500"><i class="fas fa-check-circle text-5xl mb-4"></i><p>No content pending review.</p></div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($flaggedContent as $item): ?>
                            <div class="p-4 rounded-lg border <?php echo $item['risk_level']=='high'?'border-red-500/30 bg-red-500/5':'border-yellow-500/30 bg-yellow-500/5'; ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <span class="px-2 py-1 rounded text-xs font-bold <?php echo $item['risk_level']=='high'?'bg-red-500 text-white':'bg-yellow-500 text-black'; ?>">
                                            <?php echo strtoupper($item['risk_level']); ?> RISK
                                        </span>
                                        <span class="ml-2 text-sm text-gray-400">User: <strong><?php echo htmlspecialchars($item['username']); ?></strong></span>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($item['created_at'])); ?></div>
                                </div>
                                <div class="text-sm text-gray-300 mb-3 bg-black/20 p-3 rounded italic">"<?php echo htmlspecialchars($item['reason']); ?>"</div>
                                <div class="flex gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="log_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="action" value="approved">
                                        <input type="hidden" name="content_type" value="<?php echo $item['content_type']; ?>">
                                        <input type="hidden" name="content_id" value="<?php echo $item['content_id']; ?>">
                                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-bold">Approve</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="log_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="action" value="rejected">
                                        <input type="hidden" name="content_type" value="<?php echo $item['content_type']; ?>">
                                        <input type="hidden" name="content_id" value="<?php echo $item['content_id']; ?>">
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-bold">Reject</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
</body>
</html>
