<?php
session_start();
require_once '../config/database.php';
require_once 'PerplexityClient.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
$query = "SELECT id, username, email, is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user || !$current_user['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

// Get pending counts for badges
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

// Get API key from settings
$stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'");
$stmt->execute();
$api_key_setting = $stmt->fetch();
$api_key = $api_key_setting['setting_value'] ?? '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_api_key'])) {
        $new_api_key = trim($_POST['api_key']);
        $query = "INSERT INTO site_settings (setting_key, setting_value, updated_by) 
                  VALUES ('perplexity_api_key', :key, :user_id) 
                  ON DUPLICATE KEY UPDATE setting_value = :key2, updated_by = :user_id2";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':key', $new_api_key);
        $stmt->bindParam(':key2', $new_api_key);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':user_id2', $_SESSION['user_id']);

        if ($stmt->execute()) {
            $success = 'API Key saved successfully!';
            $api_key = $new_api_key;
        } else {
            $error = 'Failed to save API key';
        }
    }

    if (isset($_POST['scan_listing'])) {
        if (empty($api_key)) {
            $error = 'Please configure Perplexity API key first!';
        } else {
            $listing_id = $_POST['listing_id'];

            // Get listing data
            $query = "SELECT l.*, u.username FROM listings l 
                      LEFT JOIN users u ON l.user_id = u.id 
                      WHERE l.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $listing_id);
            $stmt->execute();
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($listing) {
                $client = new PerplexityClient($api_key);
                $content = "Title: " . $listing['title'] . "\n\nDescription: " . $listing['description'];
                $context = "Posted by: " . $listing['username'] . ", Category: " . $listing['category_id'];

                $result = $client->scanContent($content, 'listing', $context);

                if (!isset($result['error'])) {
                    // Save scan result
                    $query = "INSERT INTO ai_content_scans 
                              (content_type, content_id, content_preview, is_safe, risk_level, 
                               violations, confidence_score, reason, recommended_action, scanned_by) 
                              VALUES ('listing', :content_id, :preview, :is_safe, :risk_level, 
                                      :violations, :confidence, :reason, :action, :scanned_by)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':content_id', $listing_id);
                    $preview = substr($listing['title'], 0, 100);
                    $stmt->bindParam(':preview', $preview);
                    $stmt->bindParam(':is_safe', $result['is_safe'], PDO::PARAM_BOOL);
                    $stmt->bindParam(':risk_level', $result['risk_level']);
                    $violations = json_encode($result['violations']);
                    $stmt->bindParam(':violations', $violations);
                    $stmt->bindParam(':confidence', $result['confidence_score']);
                    $stmt->bindParam(':reason', $result['reason']);
                    $stmt->bindParam(':action', $result['recommended_action']);
                    $stmt->bindParam(':scanned_by', $_SESSION['user_id']);
                    $stmt->execute();

                    $success = 'Listing scanned successfully! Risk Level: ' . strtoupper($result['risk_level']);
                } else {
                    $error = 'API Error: ' . ($result['error'] ?? 'Unknown error');
                }
            }
        }
    }

    if (isset($_POST['batch_scan'])) {
        if (empty($api_key)) {
            $error = 'Please configure Perplexity API key first!';
        } else {
            $status = $_POST['scan_status'] ?? 'pending';
            $limit = min((int)$_POST['scan_limit'], 50); // Max 50 at once

            // Get unscanned listings
            $query = "SELECT l.id, l.title, l.description, u.username 
                      FROM listings l 
                      LEFT JOIN users u ON l.user_id = u.id 
                      LEFT JOIN ai_content_scans s ON s.content_type = 'listing' AND s.content_id = l.id
                      WHERE l.status = :status AND s.id IS NULL
                      LIMIT :limit";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($listings) > 0) {
                $client = new PerplexityClient($api_key);
                $scanned = 0;

                foreach ($listings as $listing) {
                    $content = "Title: " . $listing['title'] . "\n\nDescription: " . $listing['description'];
                    $context = "Posted by: " . $listing['username'];

                    $result = $client->scanContent($content, 'listing', $context);

                    if (!isset($result['error'])) {
                        $query = "INSERT INTO ai_content_scans 
                                  (content_type, content_id, content_preview, is_safe, risk_level, 
                                   violations, confidence_score, reason, recommended_action, scanned_by) 
                                  VALUES ('listing', :content_id, :preview, :is_safe, :risk_level, 
                                          :violations, :confidence, :reason, :action, :scanned_by)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':content_id', $listing['id']);
                        $preview = substr($listing['title'], 0, 100);
                        $stmt->bindParam(':preview', $preview);
                        $stmt->bindParam(':is_safe', $result['is_safe'], PDO::PARAM_BOOL);
                        $stmt->bindParam(':risk_level', $result['risk_level']);
                        $violations = json_encode($result['violations']);
                        $stmt->bindParam(':violations', $violations);
                        $stmt->bindParam(':confidence', $result['confidence_score']);
                        $stmt->bindParam(':reason', $result['reason']);
                        $stmt->bindParam(':action', $result['recommended_action']);
                        $stmt->bindParam(':scanned_by', $_SESSION['user_id']);
                        $stmt->execute();
                        $scanned++;
                    }

                    usleep(500000); // 0.5 second delay between requests
                }

                $success = "Successfully scanned {$scanned} listings!";
            } else {
                $error = 'No listings found to scan';
            }
        }
    }

    if (isset($_POST['take_action'])) {
        $scan_id = $_POST['scan_id'];
        $admin_action = $_POST['admin_action'];
        $admin_notes = trim($_POST['admin_notes']);

        $query = "UPDATE ai_content_scans SET admin_action = :action, admin_notes = :notes, 
                  reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':action', $admin_action);
        $stmt->bindParam(':notes', $admin_notes);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':id', $scan_id);

        if ($stmt->execute()) {
            // If action is reject, update the actual listing
            if ($admin_action === 'reject') {
                $query = "SELECT content_id FROM ai_content_scans WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $scan_id);
                $stmt->execute();
                $scan = $stmt->fetch();

                if ($scan) {
                    $query = "UPDATE listings SET status = 'rejected' WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $scan['content_id']);
                    $stmt->execute();
                }
            }
            $success = 'Action recorded successfully!';
        }
    }
}

// Get scan statistics
$stats = [];
$stmt = $db->prepare("SELECT COUNT(*) as count FROM ai_content_scans");
$stmt->execute();
$stats['total_scans'] = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM ai_content_scans WHERE risk_level IN ('high', 'critical')");
$stmt->execute();
$stats['high_risk'] = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM ai_content_scans WHERE admin_action IS NULL");
$stmt->execute();
$stats['pending_review'] = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM ai_content_scans WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['today_scans'] = $stmt->fetch()['count'];

// Get recent scans
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$risk_filter = $_GET['risk'] ?? 'all';

$where_clause = '';
$params = [];
if ($risk_filter !== 'all') {
    $where_clause = 'WHERE s.risk_level = :risk';
    $params['risk'] = $risk_filter;
}

$query = "SELECT s.*, u.username as scanned_by_name, r.username as reviewed_by_name
          FROM ai_content_scans s
          LEFT JOIN users u ON s.scanned_by = u.id
          LEFT JOIN users r ON s.reviewed_by = r.id
          $where_clause
          ORDER BY s.created_at DESC
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total pages
$query = "SELECT COUNT(*) as count FROM ai_content_scans $where_clause";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->execute();
$total_scans = $stmt->fetch()['count'];
$total_pages = ceil($total_scans / $per_page);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Content Scanner - trubl Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a'
                        }
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
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">trubl</span>
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
                <li><a href="dashboard.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-tachometer-alt w-5 h-5"></i><span class="ml-3">Dashboard</span></a></li>
                <li><a href="users.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-users w-5 h-5"></i><span class="ml-3">Users</span></a></li>
                <li><a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-list w-5 h-5"></i><span class="ml-3">Listings</span></a></li>
                <li><a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                    <i class="fas fa-shield-alt w-5 h-5"></i><span class="ml-3">Verifications</span>
                    <?php if ($pending_counts['verifications'] > 0): ?>
                    <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
                        <?php echo $pending_counts['verifications']; ?>
                    </span>
                    <?php endif; ?>
                </a></li>
                <li><a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                    <i class="fas fa-gem w-5 h-5"></i><span class="ml-3">Upgrades</span>
                    <?php if ($pending_counts['upgrades'] > 0): ?>
                    <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                        <?php echo $pending_counts['upgrades']; ?>
                    </span>
                    <?php endif; ?>
                </a></li>
                <li><a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                    <i class="fas fa-flag w-5 h-5"></i><span class="ml-3">Reports</span>
                    <?php if ($pending_counts['reports'] > 0): ?>
                    <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                        <?php echo $pending_counts['reports']; ?>
                    </span>
                    <?php endif; ?>
                </a></li>
                <li><a href="ai-scanner.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg">
                    <i class="fas fa-robot w-5 h-5"></i><span class="ml-3">AI Scanner</span></a></li>
                <li><a href="categories.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-tags w-5 h-5"></i><span class="ml-3">Categories</span></a></li>
                <li><a href="settings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-cog w-5 h-5"></i><span class="ml-3">Settings</span></a></li>
            </ul>

            <div class="pt-4 mt-4 space-y-2 border-t border-gray-700">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-home w-5 h-5"></i><span class="ml-3">Back to Site</span></a>
                <a href="../logout.php" class="flex items-center p-3 text-red-400 rounded-lg hover:bg-red-900/20">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i><span class="ml-3">Logout</span></a>
            </div>
        </div>
    </aside>

    <div class="sm:ml-64">
        <!-- Top Bar -->
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4 py-3 lg:px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-robot text-purple-400 mr-3"></i> AI Content Scanner
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Powered by Perplexity AI - Scan and moderate content automatically</p>
                    </div>
                    <?php if ($stats['pending_review'] > 0): ?>
                    <span class="px-4 py-2 bg-orange-500/20 text-orange-400 rounded-lg text-sm font-semibold animate-pulse">
                        <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo $stats['pending_review']; ?> Pending Review
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <main class="p-4 lg:p-6">
            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="p-4 mb-4 text-sm text-green-400 rounded-lg bg-green-900/20 border border-green-500/50" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="p-4 mb-4 text-sm text-red-400 rounded-lg bg-red-900/20 border border-red-500/50" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="p-6 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-scan text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['total_scans']); ?></h3>
                    <p class="text-purple-100 text-sm">Total Scans</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['high_risk']); ?></h3>
                    <p class="text-red-100 text-sm">High Risk Detected</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-clock text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['pending_review']); ?></h3>
                    <p class="text-orange-100 text-sm">Pending Review</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-calendar-day text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($stats['today_scans']); ?></h3>
                    <p class="text-blue-100 text-sm">Today's Scans</p>
                </div>
            </div>

            <!-- Actions Panel -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- API Configuration -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-key text-blue-400 mr-2"></i> API Configuration
                    </h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Perplexity API Key</label>
                            <input type="password" name="api_key" value="<?php echo htmlspecialchars($api_key); ?>" 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="pplx-xxxxxxxxxxxxx">
                            <p class="mt-1 text-xs text-gray-400">Get your API key from <a href="https://docs.perplexity.ai" target="_blank" class="text-blue-400 hover:underline">Perplexity AI</a></p>
                        </div>
                        <button type="submit" name="save_api_key" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all">
                            <i class="fas fa-save mr-2"></i>Save API Key
                        </button>
                    </form>
                </div>

                <!-- Scan Single Listing -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-search text-green-400 mr-2"></i> Scan Single Listing
                    </h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Listing ID</label>
                            <input type="number" name="listing_id" required 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500"
                                   placeholder="Enter listing ID">
                        </div>
                        <button type="submit" name="scan_listing" <?php echo empty($api_key) ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-all">
                            <i class="fas fa-search mr-2"></i>Scan Now
                        </button>
                    </form>
                </div>

                <!-- Batch Scan -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-layer-group text-purple-400 mr-2"></i> Batch Scan
                    </h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                            <select name="scan_status" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                                <option value="pending">Pending Listings</option>
                                <option value="active">Active Listings</option>
                                <option value="all">All Listings</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Limit (max 50)</label>
                            <input type="number" name="scan_limit" value="10" min="1" max="50" 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>
                        <button type="submit" name="batch_scan" <?php echo empty($api_key) ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-all">
                            <i class="fas fa-layer-group mr-2"></i>Batch Scan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                    <a href="?risk=all" class="px-4 py-2 <?php echo $risk_filter === 'all' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-list mr-2"></i>All Scans
                    </a>
                    <a href="?risk=critical" class="px-4 py-2 <?php echo $risk_filter === 'critical' ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-skull-crossbones mr-2"></i>Critical
                    </a>
                    <a href="?risk=high" class="px-4 py-2 <?php echo $risk_filter === 'high' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-exclamation-triangle mr-2"></i>High Risk
                    </a>
                    <a href="?risk=medium" class="px-4 py-2 <?php echo $risk_filter === 'medium' ? 'bg-yellow-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-exclamation-circle mr-2"></i>Medium Risk
                    </a>
                    <a href="?risk=low" class="px-4 py-2 <?php echo $risk_filter === 'low' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-check-circle mr-2"></i>Low Risk
                    </a>
                </div>
            </div>

            <!-- Scan Results -->
            <div class="space-y-4">
                <?php if (count($scans) > 0): ?>
                    <?php foreach ($scans as $scan): ?>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-purple-500/50 transition-all">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="px-3 py-1 text-xs bg-purple-500/20 text-purple-400 rounded-full border border-purple-500/50">
                                            <i class="fas fa-<?php echo $scan['content_type'] === 'listing' ? 'list' : 'file'; ?> mr-1"></i>
                                            <?php echo ucfirst($scan['content_type']); ?> #<?php echo $scan['content_id']; ?>
                                        </span>

                                        <?php
                                        $risk_colors = [
                                            'critical' => 'bg-red-500/20 text-red-400 border-red-500/50',
                                            'high' => 'bg-orange-500/20 text-orange-400 border-orange-500/50',
                                            'medium' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/50',
                                            'low' => 'bg-green-500/20 text-green-400 border-green-500/50'
                                        ];
                                        $color = $risk_colors[$scan['risk_level']] ?? 'bg-gray-500/20 text-gray-400';
                                        ?>
                                        <span class="px-3 py-1 text-xs rounded-full border <?php echo $color; ?>">
                                            <i class="fas fa-shield-alt mr-1"></i>
                                            <?php echo strtoupper($scan['risk_level']); ?> RISK
                                        </span>

                                        <span class="px-3 py-1 text-xs <?php echo $scan['is_safe'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?> rounded-full border <?php echo $scan['is_safe'] ? 'border-green-500/50' : 'border-red-500/50'; ?>">
                                            <i class="fas fa-<?php echo $scan['is_safe'] ? 'check' : 'times'; ?> mr-1"></i>
                                            <?php echo $scan['is_safe'] ? 'SAFE' : 'UNSAFE'; ?>
                                        </span>

                                        <span class="text-xs text-gray-400">
                                            <i class="fas fa-brain mr-1"></i>Confidence: <?php echo $scan['confidence_score']; ?>%
                                        </span>
                                    </div>

                                    <h3 class="text-white font-semibold mb-2"><?php echo htmlspecialchars($scan['content_preview']); ?></h3>

                                    <div class="bg-gray-700/50 p-3 rounded-lg mb-3">
                                        <p class="text-sm text-gray-300">
                                            <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                                            <?php echo htmlspecialchars($scan['reason']); ?>
                                        </p>
                                    </div>

                                    <?php 
                                    $violations = json_decode($scan['violations'], true);
                                    if (!empty($violations) && is_array($violations)): 
                                    ?>
                                    <div class="mb-3">
                                        <p class="text-xs text-gray-400 mb-2">Violations Detected:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($violations as $violation): ?>
                                            <span class="px-2 py-1 text-xs bg-red-500/10 text-red-400 rounded border border-red-500/30">
                                                <i class="fas fa-times mr-1"></i><?php echo htmlspecialchars($violation); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="flex items-center gap-4 text-xs text-gray-400">
                                        <span><i class="fas fa-user mr-1"></i>Scanned by: <?php echo htmlspecialchars($scan['scanned_by_name'] ?? 'System'); ?></span>
                                        <span><i class="fas fa-calendar mr-1"></i><?php echo date('M j, Y - g:i A', strtotime($scan['created_at'])); ?></span>
                                        <span><i class="fas fa-robot mr-1"></i>Recommended: <strong class="text-white"><?php echo ucfirst($scan['recommended_action']); ?></strong></span>
                                    </div>

                                    <?php if ($scan['admin_action']): ?>
                                    <div class="mt-3 bg-blue-900/20 border border-blue-500/50 rounded-lg p-3">
                                        <p class="text-blue-400 text-sm font-semibold mb-1">
                                            <i class="fas fa-check-circle mr-1"></i> Admin Action Taken: <?php echo ucfirst($scan['admin_action']); ?>
                                        </p>
                                        <?php if ($scan['admin_notes']): ?>
                                        <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($scan['admin_notes']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-gray-400 text-xs mt-1">
                                            by <?php echo htmlspecialchars($scan['reviewed_by_name'] ?? 'Admin'); ?> on <?php echo date('M j, Y', strtotime($scan['reviewed_at'])); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$scan['admin_action']): ?>
                                <div class="ml-4">
                                    <button onclick="showActionModal(<?php echo $scan['id']; ?>, <?php echo $scan['content_id']; ?>)" 
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-all">
                                        <i class="fas fa-gavel mr-1"></i>Take Action
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-2">No Scans Found</h3>
                    <p class="text-gray-400">Start scanning content using the tools above</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6 gap-2">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&risk=<?php echo $risk_filter; ?>" 
                   class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&risk=<?php echo $risk_filter; ?>" 
                   class="px-4 py-2 <?php echo $i === $page ? 'bg-purple-600' : 'bg-gray-800 hover:bg-gray-700'; ?> text-white rounded-lg">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&risk=<?php echo $risk_filter; ?>" 
                   class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Action Modal -->
    <div id="action-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-gray-800 rounded-lg shadow border border-gray-700">
                <div class="flex items-center justify-between p-4 md:p-5 border-b border-gray-700">
                    <h3 class="text-xl font-semibold text-white">
                        <i class="fas fa-gavel text-blue-400 mr-2"></i> Take Admin Action
                    </h3>
                    <button type="button" class="text-gray-400 hover:bg-gray-700 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center" data-modal-hide="action-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" class="p-4 md:p-5">
                    <input type="hidden" name="scan_id" id="modal_scan_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Action</label>
                        <select name="admin_action" required class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                            <option value="approve">Approve - Content is safe</option>
                            <option value="flag">Flag - Needs attention</option>
                            <option value="reject">Reject - Remove content</option>
                            <option value="ignore">Ignore - False positive</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Admin Notes</label>
                        <textarea name="admin_notes" rows="3" placeholder="Optional notes about this decision..." 
                                  class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" name="take_action" class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-lg transition-all">
                            <i class="fas fa-check mr-2"></i>Submit Action
                        </button>
                        <button type="button" data-modal-hide="action-modal" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <script>
        function showActionModal(scanId, contentId) {
            document.getElementById('modal_scan_id').value = scanId;
            const modal = new Modal(document.getElementById('action-modal'));
            modal.show();
        }

        // Auto-hide alerts
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
