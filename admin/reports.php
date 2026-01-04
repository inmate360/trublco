<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, username, email, is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$current_user || !$current_user['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Get pending counts
$pending_counts = ['verifications' => 0, 'upgrades' => 0, 'reports' => 0];
$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_verifications WHERE status = 'pending'");
$stmt->execute();
$pending_counts['verifications'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'pending'");
$stmt->execute();
$pending_counts['upgrades'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stmt->execute();
$pending_counts['reports'] = $stmt->fetch()['count'] ?? 0;

// Handle report actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['resolve_report'])) {
        $report_id = $_POST['report_id'];
        $action_taken = $_POST['action_taken'] ?? 'Report reviewed';

        $query = "UPDATE reports SET status = 'resolved', action_taken = :action, resolved_by = :admin_id, resolved_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':action', $action_taken);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':id', $report_id);
        if($stmt->execute()) {
            $success = 'Report resolved!';
        }
    }

    if(isset($_POST['dismiss_report'])) {
        $report_id = $_POST['report_id'];
        $query = "UPDATE reports SET status = 'dismissed', resolved_by = :admin_id, resolved_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':id', $report_id);
        if($stmt->execute()) {
            $success = 'Report dismissed!';
        }
    }

    if(isset($_POST['delete_report'])) {
        $report_id = $_POST['report_id'];
        $query = "DELETE FROM reports WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $report_id);
        if($stmt->execute()) {
            $success = 'Report deleted!';
        }
    }
}

// Get report stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports");
$stmt->execute();
$total_reports = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stmt->execute();
$pending_reports = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'resolved'");
$stmt->execute();
$resolved_reports = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$today_reports = $stmt->fetch()['count'];

// Pagination and filters
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$status_filter = $_GET['status'] ?? 'all';

// Build WHERE clause
$where_clause = '';
$params = [];

if($status_filter != 'all') {
    $where_clause = "WHERE r.status = :status";
    $params[':status'] = $status_filter;
}

// Get total
$query = "SELECT COUNT(*) as count FROM reports r $where_clause";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_filtered = $stmt->fetch()['count'];
$total_pages = ceil($total_filtered / $per_page);

// Get reports
$query = "SELECT r.*, 
          reporter.username as reporter_name,
          reported.username as reported_name,
          admin.username as resolved_by_name,
          l.title as listing_title
          FROM reports r 
          LEFT JOIN users reporter ON r.reporter_id = reporter.id
          LEFT JOIN users reported ON r.reported_id = reported.id
          LEFT JOIN users admin ON r.resolved_by = admin.id
          LEFT JOIN listings l ON r.listing_id = l.id
          $where_clause
          ORDER BY r.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status counts
$counts_query = "SELECT status, COUNT(*) as count FROM reports GROUP BY status";
$stmt = $db->prepare($counts_query);
$stmt->execute();
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Management - trubl Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {'50': '#eff6ff', '100': '#dbeafe', '200': '#bfdbfe', '300': '#93c5fd', '400': '#60a5fa', '500': '#3b82f6', '600': '#2563eb', '700': '#1d4ed8', '800': '#1e40af', '900': '#1e3a8a'}
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900">

    <!-- Sidebar -->
    <aside class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0 bg-gray-800 border-r border-gray-700">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <div class="mb-5 px-3 py-2">
                <a href="../index.php" class="flex items-center">
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">⚡ trubl</span>
                </a>
                <p class="mt-1 text-xs text-gray-400">Admin Control Panel</p>
            </div>

            <div class="mb-4 p-4 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg">
                <div class="flex items-center">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-blue-600 font-bold text-xl">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-gray-800 rounded-full"></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($current_user['username']); ?></p>
                        <p class="text-xs text-blue-100">Administrator</p>
                    </div>
                </div>
            </div>

            <ul class="space-y-2 font-medium">
                <li><a href="dashboard.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-tachometer-alt w-5 h-5"></i><span class="ml-3">Dashboard</span></a></li>
                <li><a href="users.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-users w-5 h-5"></i><span class="ml-3">Users</span></a></li>
                <li><a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-list w-5 h-5"></i><span class="ml-3">Listings</span></a></li>
                <li><a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-shield-alt w-5 h-5"></i><span class="ml-3">Verifications</span><?php if ($pending_counts['verifications'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse"><?php echo $pending_counts['verifications']; ?></span><?php endif; ?></a></li>
                <li><a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-gem w-5 h-5"></i><span class="ml-3">Upgrades</span><?php if ($pending_counts['upgrades'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo $pending_counts['upgrades']; ?></span><?php endif; ?></a></li>
                <li><a href="reports.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg relative"><i class="fas fa-flag w-5 h-5"></i><span class="ml-3">Reports</span></a></li>
                <li><a href="categories.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-tags w-5 h-5"></i><span class="ml-3">Categories</span></a></li>
                <li><a href="settings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-cog w-5 h-5"></i><span class="ml-3">Settings</span></a></li>
            </ul>

            <div class="pt-4 mt-4 space-y-2 border-t border-gray-700">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-home w-5 h-5"></i><span class="ml-3">Back to Site</span></a>
                <a href="../logout.php" class="flex items-center p-3 text-red-400 rounded-lg hover:bg-red-900/20"><i class="fas fa-sign-out-alt w-5 h-5"></i><span class="ml-3">Logout</span></a>
            </div>
        </div>
    </aside>

    <div class="sm:ml-64">
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4 py-3 lg:px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-flag text-red-400 mr-3"></i>
                            Report Management
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Review and handle user reports</p>
                    </div>
                    <?php if ($pending_reports > 0): ?>
                        <span class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg text-sm font-semibold animate-pulse">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?php echo $pending_reports; ?> Pending
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <main class="p-4 lg:p-6">
            <?php if ($success): ?>
                <div class="p-4 mb-4 text-sm text-green-400 rounded-lg bg-green-900/20 border border-green-500/50" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="p-6 bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-flag text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($total_reports); ?></h3>
                    <p class="text-red-100 text-sm">Total Reports</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-clock text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($pending_reports); ?></h3>
                    <p class="text-orange-100 text-sm">Pending Review</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-check-circle text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($resolved_reports); ?></h3>
                    <p class="text-green-100 text-sm">Resolved</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-calendar-day text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($today_reports); ?></h3>
                    <p class="text-blue-100 text-sm">Today's Reports</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                    <a href="?status=all" 
                       class="px-4 py-2 <?php echo $status_filter == 'all' ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-list mr-2"></i>All (<?php echo array_sum($status_counts); ?>)
                    </a>
                    <a href="?status=pending" 
                       class="px-4 py-2 <?php echo $status_filter == 'pending' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-clock mr-2"></i>Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                    </a>
                    <a href="?status=resolved" 
                       class="px-4 py-2 <?php echo $status_filter == 'resolved' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-check-circle mr-2"></i>Resolved (<?php echo $status_counts['resolved'] ?? 0; ?>)
                    </a>
                    <a href="?status=dismissed" 
                       class="px-4 py-2 <?php echo $status_filter == 'dismissed' ? 'bg-gray-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-times-circle mr-2"></i>Dismissed (<?php echo $status_counts['dismissed'] ?? 0; ?>)
                    </a>
                </div>
            </div>

            <!-- Reports Grid -->
            <?php if (count($reports) > 0): ?>
                <div class="grid grid-cols-1 gap-4 mb-6">
                    <?php foreach ($reports as $report): ?>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-red-500/50 transition-all">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <span class="px-3 py-1 text-xs bg-red-500/20 text-red-400 rounded-full border border-red-500/50">
                                            <i class="fas fa-flag mr-1"></i>
                                            <?php echo ucfirst($report['report_type'] ?? 'Report'); ?>
                                        </span>
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-orange-500/20 text-orange-400 border-orange-500/50',
                                            'resolved' => 'bg-green-500/20 text-green-400 border-green-500/50',
                                            'dismissed' => 'bg-gray-500/20 text-gray-400 border-gray-500/50'
                                        ];
                                        $color = $status_colors[$report['status']] ?? 'bg-gray-500/20 text-gray-400';
                                        ?>
                                        <span class="px-3 py-1 text-xs rounded-full border <?php echo $color; ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                        <span class="text-sm text-gray-400">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('M j, Y - g:i A', strtotime($report['created_at'])); ?>
                                        </span>
                                    </div>

                                    <h3 class="text-lg font-bold text-white mb-2">
                                        <?php echo htmlspecialchars($report['reason']); ?>
                                    </h3>

                                    <?php if($report['description']): ?>
                                        <p class="text-gray-300 text-sm mb-4 bg-gray-700/50 p-3 rounded-lg">
                                            <?php echo htmlspecialchars($report['description']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                        <div class="bg-gray-700/50 p-3 rounded-lg">
                                            <p class="text-gray-400 mb-1">Reporter</p>
                                            <p class="text-white font-semibold">
                                                <i class="fas fa-user mr-1"></i>
                                                <?php echo htmlspecialchars($report['reporter_name'] ?? 'Unknown'); ?>
                                            </p>
                                        </div>

                                        <div class="bg-gray-700/50 p-3 rounded-lg">
                                            <p class="text-gray-400 mb-1">Reported User</p>
                                            <p class="text-white font-semibold">
                                                <i class="fas fa-user-times mr-1"></i>
                                                <?php echo htmlspecialchars($report['reported_name'] ?? 'Unknown'); ?>
                                            </p>
                                        </div>

                                        <?php if($report['listing_title']): ?>
                                        <div class="bg-gray-700/50 p-3 rounded-lg">
                                            <p class="text-gray-400 mb-1">Related Listing</p>
                                            <p class="text-white font-semibold">
                                                <i class="fas fa-list mr-1"></i>
                                                <?php echo htmlspecialchars($report['listing_title']); ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if($report['action_taken']): ?>
                                        <div class="mt-3 bg-green-900/20 border border-green-500/50 rounded-lg p-3">
                                            <p class="text-green-400 text-sm font-semibold mb-1">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Action Taken
                                            </p>
                                            <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($report['action_taken']); ?></p>
                                            <?php if($report['resolved_by_name']): ?>
                                                <p class="text-gray-400 text-xs mt-1">
                                                    by <?php echo htmlspecialchars($report['resolved_by_name']); ?> on 
                                                    <?php echo date('M j, Y', strtotime($report['resolved_at'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if($report['status'] == 'pending'): ?>
                                <div class="ml-4 flex flex-col gap-2">
                                    <button onclick="showResolveModal(<?php echo $report['id']; ?>)" 
                                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-all">
                                        <i class="fas fa-check mr-1"></i>Resolve
                                    </button>

                                    <form method="POST" class="inline">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" name="dismiss_report" 
                                                class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-all">
                                            <i class="fas fa-times mr-1"></i>Dismiss
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <div class="ml-4">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" name="delete_report" 
                                                onclick="return confirm('Delete this report?')" 
                                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-all">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-12 text-center">
                    <i class="fas fa-check-circle text-6xl text-green-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-2">All Clear!</h3>
                    <p class="text-gray-400">No reports match your current filter</p>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6 gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>" 
                       class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" 
                       class="px-4 py-2 <?php echo $i == $page ? 'bg-red-600' : 'bg-gray-800 hover:bg-gray-700'; ?> text-white rounded-lg">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>" 
                       class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Resolve Modal -->
    <div id="resolve-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-gray-800 rounded-lg shadow border border-gray-700">
                <div class="flex items-center justify-between p-4 md:p-5 border-b border-gray-700">
                    <h3 class="text-xl font-semibold text-white">
                        <i class="fas fa-check-circle text-green-400 mr-2"></i>
                        Resolve Report
                    </h3>
                    <button type="button" class="text-gray-400 hover:bg-gray-700 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center" data-modal-hide="resolve-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="POST" class="p-4 md:p-5">
                    <input type="hidden" name="report_id" id="resolve_report_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Action Taken *</label>
                        <textarea name="action_taken" rows="4" required 
                                  placeholder="Describe the action taken to resolve this report..."
                                  class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-green-500"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" name="resolve_report" 
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold rounded-lg transition-all">
                            <i class="fas fa-check mr-2"></i>Resolve Report
                        </button>
                        <button type="button" data-modal-hide="resolve-modal" 
                                class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <script>
        function showResolveModal(reportId) {
            document.getElementById('resolve_report_id').value = reportId;
            const modal = new Modal(document.getElementById('resolve-modal'));
            modal.show();
        }

        setTimeout(() => {
            document.querySelectorAll('[role="alert"]').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
