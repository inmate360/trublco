<?php
session_start();
require_once '../config/database.php';

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

// Helper function for safe queries
function safeQuery($db, $query, $params = []) {
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " | Query: " . $query);
        return false;
    }
}

// Get comprehensive statistics
$stats = [];

// User Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
$stats['new_users_today'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stats['new_users_week'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['active_users_24h'] = $result ? $result->fetch()['count'] : 0;

// Listing Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings");
$stats['total_listings'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE status = 'active'");
$stats['active_listings'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE status = 'pending'");
$stats['pending_listings'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE DATE(created_at) = CURDATE()");
$stats['new_listings_today'] = $result ? $result->fetch()['count'] : 0;

// Message Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM messages");
$stats['total_messages'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()");
$stats['messages_today'] = $result ? $result->fetch()['count'] : 0;

// Premium Membership Statistics
$result = safeQuery($db, "SELECT COUNT(DISTINCT user_id) as count FROM user_subscriptions WHERE status = 'active'");
$stats['premium_members'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'pending'");
$stats['pending_upgrades'] = $result ? $result->fetch()['count'] : 0;

// Revenue Statistics
$result = safeQuery($db, "SELECT SUM(amount) as total FROM user_subscriptions WHERE status = 'active'");
$stats['total_revenue'] = $result ? ($result->fetch()['total'] ?? 0) : 0;

$result = safeQuery($db, "SELECT SUM(amount) as total FROM user_subscriptions WHERE status = 'active' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats['revenue_30days'] = $result ? ($result->fetch()['total'] ?? 0) : 0;

// Report Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stats['pending_reports'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM reports WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()");
$stats['resolved_reports_today'] = $result ? $result->fetch()['count'] : 0;

// Featured Ads Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM featured_ads WHERE status = 'active' AND end_date > NOW()");
$stats['active_featured'] = $result ? $result->fetch()['count'] : 0;

// Verification Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM user_verifications WHERE status = 'pending'");
$stats['pending_verifications'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM user_verifications WHERE status = 'approved'");
$stats['approved_verifications'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM user_verifications");
$stats['total_verifications'] = $result ? $result->fetch()['count'] : 0;

// System Health
$stats['database_size'] = 0;
$result = safeQuery($db, "SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE()");
if ($result) {
    $stats['database_size'] = round($result->fetch()['size_mb'] ?? 0, 2);
}

// Recent Activity Data
$recent_users = [];
$result = safeQuery($db, "SELECT id, username, email, created_at, last_active FROM users ORDER BY created_at DESC LIMIT 10");
if ($result) {
    $recent_users = $result->fetchAll(PDO::FETCH_ASSOC);
}

$recent_listings = [];
$result = safeQuery($db, "SELECT l.id, l.title, l.status, l.created_at, u.username, c.name as city_name 
          FROM listings l 
          LEFT JOIN users u ON l.user_id = u.id 
          LEFT JOIN cities c ON l.city_id = c.id 
          ORDER BY l.created_at DESC LIMIT 10");
if ($result) {
    $recent_listings = $result->fetchAll(PDO::FETCH_ASSOC);
}

$pending_reports_list = [];
$result = safeQuery($db, "SELECT r.id, r.reason, r.created_at, r.report_type, u.username as reporter 
          FROM reports r 
          LEFT JOIN users u ON r.reporter_id = u.id 
          WHERE r.status = 'pending' 
          ORDER BY r.created_at DESC LIMIT 10");
if ($result) {
    $pending_reports_list = $result->fetchAll(PDO::FETCH_ASSOC);
}

// Handle New Post Submission (Twitter-like)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    $content = trim($_POST['content'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if (!empty($content)) {
        try {
            // Handle Image Upload
            $image_path = null;
            if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/posts/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_ext = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('post_') . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['post_image']['tmp_name'], $target_path)) {
                    $image_path = 'uploads/posts/' . $file_name;
                }
            }

            // Insert into stories table (using it as the feed)
            // Note: We use 'cover_image' and 'author_name' for compatibility with existing schema
            $query = "INSERT INTO stories (title, content, author_name, status, cover_image, created_at) 
                      VALUES (:title, :content, :author, 'approved', :image, NOW())";
            $stmt = $db->prepare($query);
            $title = substr($content, 0, 50) . (strlen($content) > 50 ? '...' : '');
            $stmt->execute([
                'title' => $title,
                'content' => $content,
                'author' => $current_user['username'],
                'image' => $image_path ? basename($image_path) : null
            ]);
            
            header('Location: dashboard.php?post_success=1');
            exit();
        } catch (PDOException $e) {
            error_log("Post creation error: " . $e->getMessage());
        }
    }
}

// Fetch Feed (Twitter-like)
$feed_posts = [];
try {
    // Using author_name and cover_image for compatibility
    $query = "SELECT s.*, 
              (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as likes,
              (SELECT COUNT(*) FROM story_comments WHERE story_id = s.id) as comments
              FROM stories s
              WHERE s.status = 'approved'
              ORDER BY s.created_at DESC LIMIT 20";
    $stmt = $db->query($query);
    $feed_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Feed fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - trubl</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Flowbite -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card-hover {
            transition: all 0.3s ease;
        }

        .stat-card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        .shimmer {
            animation: shimmer 2s infinite;
            background: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
            background-size: 1000px 100%;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0">
        <div class="h-full px-3 py-4 overflow-y-auto bg-gray-800 border-r border-gray-700">
            <!-- Logo -->
            <div class="mb-5 px-3 py-2">
                <a href="../index.php" class="flex items-center">
                    <span class="self-center text-2xl font-bold whitespace-nowrap gradient-bg bg-clip-text text-transparent">
                        ⚡ trubl
                    </span>
                </a>
                <p class="mt-1 text-xs text-gray-400">Admin Control Panel</p>
            </div>

            <!-- User Profile -->
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

            <!-- Navigation -->
            <ul class="space-y-2 font-medium">
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg group">
                        <i class="fas fa-tachometer-alt w-5 h-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="users.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                        <i class="fas fa-users w-5 h-5"></i>
                        <span class="ml-3">Users</span>
                    </a>
                </li>

                <li>
                    <a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                        <i class="fas fa-list w-5 h-5"></i>
                        <span class="ml-3">Listings</span>
                    </a>
                </li>

                <li>
                    <a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group relative">
                        <i class="fas fa-shield-alt w-5 h-5"></i>
                        <span class="ml-3">Verifications</span>
                        <?php if ($stats['pending_verifications'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 ml-auto text-xs font-semibold text-white bg-red-500 rounded-full">
                                <?php echo $stats['pending_verifications']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group relative">
                        <i class="fas fa-gem w-5 h-5"></i>
                        <span class="ml-3">Upgrades</span>
                        <?php if ($stats['pending_upgrades'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 ml-auto text-xs font-semibold text-white bg-red-500 rounded-full">
                                <?php echo $stats['pending_upgrades']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group relative">
                        <i class="fas fa-flag w-5 h-5"></i>
                        <span class="ml-3">Reports</span>
                        <?php if ($stats['pending_reports'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 ml-auto text-xs font-semibold text-white bg-red-500 rounded-full">
                                <?php echo $stats['pending_reports']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="categories.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                        <i class="fas fa-tags w-5 h-5"></i>
                        <span class="ml-3">Categories</span>
                    </a>
                </li>

                <li>
                    <a href="settings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                        <i class="fas fa-cog w-5 h-5"></i>
                        <span class="ml-3">Settings</span>
                    </a>
                </li>

                <li>
                    <a href="mod.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                        <i class="fas fa-shield-alt w-5 h-5"></i>
                        <span class="ml-3">Content Moderation</span>
                    </a>
                </li>

                <li>
                    <a href="news.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                        <i class="fas fa-bullhorn w-5 h-5"></i>
                        <span class="ml-3">News & Announcements</span>
                    </a>
                </li>
            </ul>

            <!-- Bottom Actions -->
            <div class="pt-4 mt-4 space-y-2 border-t border-gray-700">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group">
                    <i class="fas fa-home w-5 h-5"></i>
                    <span class="ml-3">Back to Site</span>
                </a>

                <a href="../logout.php" class="flex items-center p-3 text-red-400 rounded-lg hover:bg-red-900/20 group">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i>
                    <span class="ml-3">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="sm:ml-64">
        <!-- Top Navigation Bar -->
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4 py-3 lg:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <button data-drawer-target="sidebar" data-drawer-toggle="sidebar" class="inline-flex items-center p-2 text-sm text-gray-400 rounded-lg sm:hidden hover:bg-gray-700">
                            <i class="fas fa-bars w-6 h-6"></i>
                        </button>
                        <div class="ml-3">
                            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
                            <p class="text-sm text-gray-400">Welcome back, <?php echo htmlspecialchars($current_user['username']); ?></p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative hidden md:block">
                            <input type="text" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-64 pl-10 p-2.5" placeholder="Search...">
                            <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
                        </div>

                        <!-- Notifications -->
                        <button type="button" class="relative p-2 text-gray-400 rounded-lg hover:bg-gray-700">
                            <i class="fas fa-bell text-xl"></i>
                            <?php 
                            $total_pending = $stats['pending_verifications'] + $stats['pending_upgrades'] + $stats['pending_reports'];
                            if ($total_pending > 0): 
                            ?>
                                <span class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                                    <?php echo $total_pending; ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Profile Dropdown -->
                        <button type="button" class="flex text-sm bg-gray-700 rounded-full focus:ring-4 focus:ring-gray-600" data-dropdown-toggle="dropdown-user">
                            <span class="sr-only">Open user menu</span>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                            </div>
                        </button>

                        <!-- Dropdown menu -->
                        <div class="z-50 hidden my-4 text-base list-none bg-gray-700 divide-y divide-gray-600 rounded-lg shadow" id="dropdown-user">
                            <div class="px-4 py-3">
                                <p class="text-sm text-white"><?php echo htmlspecialchars($current_user['username']); ?></p>
                                <p class="text-sm font-medium text-gray-400 truncate"><?php echo htmlspecialchars($current_user['email']); ?></p>
                            </div>
                            <ul class="py-2">
                                <li>
                                    <a href="../profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">Profile</a>
                                </li>
                                <li>
                                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">Settings</a>
                                </li>
                                <li>
                                    <a href="news.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">News & Announcements</a>
                                </li>
                                <li>
                                    <a href="mod.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">Content Moderation</a>
                                </li>
                                <li>
                                    <a href="../logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-600">Sign out</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="p-4 lg:p-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Users -->
                <div class="p-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg stat-card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg backdrop-blur-sm">
                            <i class="fas fa-users text-2xl text-white"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-100 bg-blue-800/50 px-2 py-1 rounded-full">
                            +<?php echo $stats['new_users_today']; ?> today
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['total_users']); ?></h3>
                    <p class="text-blue-100 text-sm">Total Users</p>
                    <div class="mt-4 flex items-center text-xs text-blue-100">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span><?php echo $stats['active_users_24h']; ?> active in 24h</span>
                    </div>
                </div>

                <!-- Total Listings -->
                <div class="p-6 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg stat-card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg backdrop-blur-sm">
                            <i class="fas fa-list text-2xl text-white"></i>
                        </div>
                        <span class="text-xs font-semibold text-purple-100 bg-purple-800/50 px-2 py-1 rounded-full">
                            +<?php echo $stats['new_listings_today']; ?> today
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['total_listings']); ?></h3>
                    <p class="text-purple-100 text-sm">Total Listings</p>
                    <div class="mt-4 flex items-center text-xs text-purple-100">
                        <i class="fas fa-check-circle mr-1"></i>
                        <span><?php echo $stats['active_listings']; ?> active</span>
                    </div>
                </div>

                <!-- Pending Approvals -->
                <div class="p-6 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl shadow-lg stat-card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg backdrop-blur-sm">
                            <i class="fas fa-clock text-2xl text-white"></i>
                        </div>
                        <?php if ($stats['pending_listings'] > 0): ?>
                            <span class="text-xs font-semibold text-orange-100 bg-orange-800/50 px-2 py-1 rounded-full animate-pulse">
                                Action needed
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['pending_listings']); ?></h3>
                    <p class="text-orange-100 text-sm">Pending Approvals</p>
                    <div class="mt-4">
                        <a href="listings.php?status=pending" class="text-xs text-orange-100 hover:text-white flex items-center">
                            <span>Review now</span>
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>

                <!-- Revenue -->
                <div class="p-6 bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg stat-card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg backdrop-blur-sm">
                            <i class="fas fa-dollar-sign text-2xl text-white"></i>
                        </div>
                        <span class="text-xs font-semibold text-green-100 bg-green-800/50 px-2 py-1 rounded-full">
                            30 days
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1">$<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                    <p class="text-green-100 text-sm">Total Revenue</p>
                    <div class="mt-4 flex items-center text-xs text-green-100">
                        <i class="fas fa-trending-up mr-1"></i>
                        <span>$<?php echo number_format($stats['revenue_30days'], 0); ?> this month</span>
                    </div>
                </div>
            </div>

            <!-- Action Cards Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Verifications -->
                <a href="verifications.php" class="block p-5 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-all hover:border-blue-500 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-blue-500/20 rounded-lg group-hover:bg-blue-500 transition-all">
                            <i class="fas fa-shield-alt text-blue-400 group-hover:text-white"></i>
                        </div>
                        <?php if ($stats['pending_verifications'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
                                <?php echo $stats['pending_verifications']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h5 class="text-lg font-semibold text-white mb-1">ID Verifications</h5>
                    <p class="text-sm text-gray-400">
                        <?php echo $stats['pending_verifications']; ?> pending review
                    </p>
                </a>

                <!-- Upgrades -->
                <a href="upgrades.php" class="block p-5 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-all hover:border-purple-500 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-purple-500/20 rounded-lg group-hover:bg-purple-500 transition-all">
                            <i class="fas fa-gem text-purple-400 group-hover:text-white"></i>
                        </div>
                        <?php if ($stats['pending_upgrades'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
                                <?php echo $stats['pending_upgrades']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h5 class="text-lg font-semibold text-white mb-1">Premium Upgrades</h5>
                    <p class="text-sm text-gray-400">
                        <?php echo $stats['premium_members']; ?> active members
                    </p>
                </a>

                <!-- Reports -->
                <a href="reports.php" class="block p-5 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-all hover:border-red-500 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-red-500/20 rounded-lg group-hover:bg-red-500 transition-all">
                            <i class="fas fa-flag text-red-400 group-hover:text-white"></i>
                        </div>
                        <?php if ($stats['pending_reports'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
                                <?php echo $stats['pending_reports']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h5 class="text-lg font-semibold text-white mb-1">User Reports</h5>
                    <p class="text-sm text-gray-400">
                        <?php echo $stats['pending_reports']; ?> need attention
                    </p>
                </a>

                <!-- Messages -->
                <a href="messages.php" class="block p-5 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-all hover:border-green-500 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-green-500/20 rounded-lg group-hover:bg-green-500 transition-all">
                            <i class="fas fa-comments text-green-400 group-hover:text-white"></i>
                        </div>
                    </div>
                    <h5 class="text-lg font-semibold text-white mb-1">Messages</h5>
                    <p class="text-sm text-gray-400">
                        <?php echo number_format($stats['total_messages']); ?> total
                    </p>
                </a>
            </div>

            <!-- Twitter-like Feed Section -->
            <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-3">
                <!-- Left Column: Posting & Feed -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Create Post Card -->
                    <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-lg overflow-hidden">
                        <div class="p-4 border-b border-gray-700 bg-gray-800/50">
                            <h3 class="text-lg font-semibold text-white flex items-center">
                                <i class="fab fa-twitter text-blue-400 mr-2"></i>
                                What's happening?
                            </h3>
                        </div>
                        <div class="p-4">
                            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create_post">
                                <div class="flex gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold text-xl">
                                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <textarea name="content" rows="3" 
                                            class="w-full bg-transparent border-none text-white text-lg placeholder-gray-500 focus:ring-0 resize-none"
                                            placeholder="Share an update with the community..."></textarea>
                                        
                                        <!-- Image Preview Container -->
                                        <div id="image-preview" class="hidden mt-3 relative">
                                            <img src="" class="rounded-2xl max-h-80 w-full object-cover border border-gray-700">
                                            <button type="button" onclick="removeImage()" class="absolute top-2 right-2 bg-gray-900/80 text-white rounded-full p-1.5 hover:bg-gray-800">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>

                                        <div class="mt-4 pt-3 border-t border-gray-700 flex items-center justify-between">
                                            <div class="flex items-center space-x-4 text-blue-400">
                                                <label class="cursor-pointer hover:bg-blue-400/10 p-2 rounded-full transition-all">
                                                    <i class="far fa-image text-xl"></i>
                                                    <input type="file" name="post_image" class="hidden" accept="image/*" onchange="previewImage(this)">
                                                </label>
                                                <button type="button" class="hover:bg-blue-400/10 p-2 rounded-full transition-all" onclick="toggleEmojiPicker()">
                                                    <i class="far fa-smile text-xl"></i>
                                                </button>
                                                <div class="text-xs text-gray-500 font-medium">
                                                    <i class="fas fa-shield-alt mr-1"></i>
                                                    Protected By AI Content Moderation 🛡️
                                                </div>
                                            </div>
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-full transition-all disabled:opacity-50">
                                                Post
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Activity Feed -->
                    <div class="space-y-4">
                        <?php if (empty($feed_posts)): ?>
                            <div class="bg-gray-800 border border-gray-700 rounded-xl p-10 text-center">
                                <i class="fas fa-stream text-4xl text-gray-600 mb-3"></i>
                                <p class="text-gray-400">No activity yet. Be the first to post!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($feed_posts as $post): ?>
                                <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-lg hover:bg-gray-800/80 transition-all group">
                                    <div class="p-4">
                                        <div class="flex gap-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center text-gray-400 font-bold text-xl">
                                                    <?php echo strtoupper(substr($post['author_name'] ?? 'A', 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-bold text-white hover:underline cursor-pointer">
                                                            <?php echo htmlspecialchars($post['author_name'] ?? 'Anonymous'); ?>
                                                        </span>
                                                        <span class="text-gray-500 text-sm">
                                                            · <?php echo date('M j', strtotime($post['created_at'])); ?>
                                                        </span>
                                                    </div>
                                                    <button class="text-gray-500 hover:text-blue-400 opacity-0 group-hover:opacity-100 transition-all">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                </div>
                                                <div class="mt-2 text-white text-[15px] leading-normal whitespace-pre-wrap"><?php echo htmlspecialchars($post['content']); ?></div>
                                                
                                                <?php if (!empty($post['cover_image'])): ?>
                                                    <div class="mt-3 rounded-2xl overflow-hidden border border-gray-700">
                                                        <img src="../uploads/posts/<?php echo htmlspecialchars($post['cover_image']); ?>" class="w-full h-auto max-h-[500px] object-cover">
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mt-4 flex items-center justify-between max-w-md text-gray-500">
                                                    <button class="flex items-center gap-2 hover:text-blue-400 transition-all">
                                                        <i class="far fa-comment"></i>
                                                        <span class="text-sm"><?php echo $post['comments']; ?></span>
                                                    </button>
                                                    <button class="flex items-center gap-2 hover:text-green-400 transition-all">
                                                        <i class="fas fa-retweet"></i>
                                                        <span class="text-sm">0</span>
                                                    </button>
                                                    <button class="flex items-center gap-2 hover:text-pink-500 transition-all">
                                                        <i class="far fa-heart"></i>
                                                        <span class="text-sm"><?php echo $post['likes']; ?></span>
                                                    </button>
                                                    <button class="flex items-center gap-2 hover:text-blue-400 transition-all">
                                                        <i class="far fa-chart-bar"></i>
                                                        <span class="text-sm">0</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Sidebar Info -->
                <div class="space-y-6">
                    <!-- Recent Users -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-lg">
                    <div class="p-5 border-b border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-white">
                                <i class="fas fa-users text-blue-400 mr-2"></i>
                                Recent Users
                            </h3>
                            <a href="users.php" class="text-sm text-blue-400 hover:text-blue-300 flex items-center">
                                View all
                                <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="space-y-3">
                            <?php foreach(array_slice($recent_users, 0, 5) as $user): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition-all">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold mr-3">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($user['username']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-400">
                                            <?php 
                                            $time_ago = time() - strtotime($user['created_at']);
                                            if ($time_ago < 3600) {
                                                echo round($time_ago / 60) . 'm ago';
                                            } elseif ($time_ago < 86400) {
                                                echo round($time_ago / 3600) . 'h ago';
                                            } else {
                                                echo date('M j', strtotime($user['created_at']));
                                            }
                                            ?>
                                        </p>
                                        <a href="../profile.php?id=<?php echo $user['id']; ?>" class="text-xs text-blue-400 hover:text-blue-300">
                                            View
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Listings -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-lg">
                    <div class="p-5 border-b border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-white">
                                <i class="fas fa-list text-purple-400 mr-2"></i>
                                Recent Listings
                            </h3>
                            <a href="listings.php" class="text-sm text-blue-400 hover:text-blue-300 flex items-center">
                                View all
                                <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="space-y-3">
                            <?php foreach(array_slice($recent_listings, 0, 5) as $listing): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition-all">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-white truncate">
                                            <?php echo htmlspecialchars(substr($listing['title'], 0, 40)); ?>
                                            <?php echo strlen($listing['title']) > 40 ? '...' : ''; ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            by <?php echo htmlspecialchars($listing['username'] ?? 'Unknown'); ?>
                                        </p>
                                    </div>
                                    <div class="ml-4">
                                        <?php
                                        $status_colors = [
                                            'active' => 'bg-green-500/20 text-green-400',
                                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                                            'rejected' => 'bg-red-500/20 text-red-400'
                                        ];
                                        $color = $status_colors[$listing['status']] ?? 'bg-gray-500/20 text-gray-400';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                            <?php echo ucfirst($listing['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

                </div> <!-- End Right Column -->
            </div> <!-- End Grid -->

            <!-- System Health -->
            <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-lg">
                <div class="p-5 border-b border-gray-700">
                    <h3 class="text-lg font-semibold text-white">
                        <i class="fas fa-server text-green-400 mr-2"></i>
                        System Health
                    </h3>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="p-4 bg-gray-700/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Database</span>
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            </div>
                            <p class="text-xl font-bold text-white"><?php echo $stats['database_size']; ?> MB</p>
                        </div>

                        <div class="p-4 bg-gray-700/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Server</span>
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            </div>
                            <p class="text-xl font-bold text-white">Online</p>
                        </div>

                        <div class="p-4 bg-gray-700/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">PHP</span>
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            </div>
                            <p class="text-xl font-bold text-white"><?php echo PHP_VERSION; ?></p>
                        </div>

                        <div class="p-4 bg-gray-700/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Disk</span>
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            </div>
                            <p class="text-xl font-bold text-white">
                                <?php
                                $free = @disk_free_space('/');
                                $total = @disk_total_space('/');
                                echo $free && $total ? round(($free / $total) * 100, 1) . '%' : 'N/A';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- Footer -->
        <footer class="p-4 bg-gray-800 border-t border-gray-700 sm:ml-0">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">
                    © <?php echo date('Y'); ?> <a href="../index.php" class="hover:text-blue-400">trubl</a>. All rights reserved.
                </span>
                <span class="text-sm text-gray-400">
                    Version 2.0.0 | Last updated: <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </footer>
    </div>

    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>

    <script>
        // Image Preview Logic
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            const previewImg = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            const preview = document.getElementById('image-preview');
            const input = document.querySelector('input[name="post_image"]');
            preview.classList.add('hidden');
            preview.querySelector('img').src = '';
            input.value = '';
        }

        // Emoji Picker (Simple Placeholder for now)
        function toggleEmojiPicker() {
            const emojis = ['😊', '😂', '🔥', '❤️', '👍', '🙌', '✨', '🚀', '💯', '🤔'];
            const textarea = document.querySelector('textarea[name="content"]');
            const emoji = emojis[Math.floor(Math.random() * emojis.length)];
            
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + emoji + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
