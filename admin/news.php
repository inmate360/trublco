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

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            try {
                $query = "INSERT INTO site_news (title, content, type, is_scrolling, scroll_speed, bg_color, text_color, priority, start_date, end_date, created_by) 
                          VALUES (:title, :content, :type, :is_scrolling, :scroll_speed, :bg_color, :text_color, :priority, :start_date, :end_date, :created_by)";
                $stmt = $db->prepare($query);
                
                $is_scrolling = isset($_POST['is_scrolling']) ? 1 : 0;
                $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                
                $stmt->bindParam(':title', $_POST['title']);
                $stmt->bindParam(':content', $_POST['content']);
                $stmt->bindParam(':type', $_POST['type']);
                $stmt->bindParam(':is_scrolling', $is_scrolling, PDO::PARAM_INT);
                $stmt->bindParam(':scroll_speed', $_POST['scroll_speed'], PDO::PARAM_INT);
                $stmt->bindParam(':bg_color', $_POST['bg_color']);
                $stmt->bindParam(':text_color', $_POST['text_color']);
                $stmt->bindParam(':priority', $_POST['priority'], PDO::PARAM_INT);
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
                $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = "News item created successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error creating news: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'toggle') {
            try {
                $query = "UPDATE site_news SET is_active = NOT is_active WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                $stmt->execute();
                $success = "Status updated!";
            } catch (PDOException $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'delete') {
            try {
                $query = "DELETE FROM site_news WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                $stmt->execute();
                $success = "News item deleted!";
            } catch (PDOException $e) {
                $error = "Error deleting news: " . $e->getMessage();
            }
        }
    }
}

// Fetch all news
try {
    $query = "SELECT n.*, u.username FROM site_news n 
              LEFT JOIN users u ON n.created_by = u.id 
              ORDER BY n.priority DESC, n.created_at DESC";
    $stmt = $db->query($query);
    $news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $news_items = [];
    $error = "Error fetching news: " . $e->getMessage();
}

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

$current_page = 'news';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Management - Admin Panel</title>
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
                <li><a href="mod.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 group"><i class="fas fa-shield-virus w-5"></i><span class="ml-3">Content Moderation</span></a></li>
                <li><a href="news.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg group"><i class="fas fa-bullhorn w-5"></i><span class="ml-3">News & Announcements</span></a></li>
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
                        <h1 class="text-xl font-bold text-white">News Management</h1>
                        <p class="text-xs text-gray-400">Manage site-wide announcements</p>
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
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-white">📢 Announcements</h2>
                <button data-modal-target="create-modal" data-modal-toggle="create-modal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold transition-colors">
                    <i class="fas fa-plus mr-2"></i>Create News
                </button>
            </div>

            <?php if ($success): ?>
                <div class="p-4 mb-4 text-sm text-green-400 bg-green-900/30 border border-green-500/30 rounded-lg"><i class="fas fa-check-circle mr-2"></i><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4">Title & Content</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Display</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($news_items as $item): ?>
                        <tr class="hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-white"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($item['content']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $item['type']=='danger'?'bg-red-500/20 text-red-500':($item['type']=='warning'?'bg-yellow-500/20 text-yellow-500':'bg-blue-500/20 text-blue-500'); ?>">
                                    <?php echo strtoupper($item['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($item['is_scrolling']): ?>
                                    <span class="text-yellow-500 text-xs font-bold"><i class="fas fa-arrows-alt-h mr-1"></i>SCROLLING</span>
                                <?php else: ?>
                                    <span class="text-gray-500 text-xs font-bold">STATIC</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="flex items-center gap-2 <?php echo $item['is_active']?'text-green-500':'text-gray-500'; ?>">
                                        <i class="fas <?php echo $item['is_active']?'fa-toggle-on':'fa-toggle-off'; ?> text-xl"></i>
                                        <span class="text-xs font-bold uppercase"><?php echo $item['is_active']?'Active':'Inactive'; ?></span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this announcement?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-400"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Create Modal -->
    <div id="create-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-gray-800 rounded-xl shadow border border-gray-700">
                <div class="flex items-center justify-between p-4 border-b border-gray-700">
                    <h3 class="text-xl font-bold text-white">Create New Announcement</h3>
                    <button type="button" class="text-gray-400 hover:bg-gray-700 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center" data-modal-hide="create-modal"><i class="fas fa-times"></i></button>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="block mb-2 text-sm font-bold text-gray-400">Title</label>
                        <input type="text" name="title" required class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-bold text-gray-400">Content</label>
                        <textarea name="content" rows="3" required class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-bold text-gray-400">Type</label>
                            <select name="type" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="info">Info</option>
                                <option value="success">Success</option>
                                <option value="warning">Warning</option>
                                <option value="danger">Danger</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-bold text-gray-400">Priority</label>
                            <input type="number" name="priority" value="0" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>
                    <div class="p-4 bg-gray-900/50 rounded-lg border border-gray-700">
                        <label class="flex items-center gap-2 font-bold text-yellow-500 mb-4">
                            <input type="checkbox" name="is_scrolling" onchange="document.getElementById('scroll-opts').classList.toggle('hidden')" class="w-4 h-4 bg-gray-700 border-gray-600 rounded text-blue-600">
                            Enable Scrolling Marquee
                        </label>
                        <div id="scroll-opts" class="hidden grid grid-cols-3 gap-4">
                            <div><label class="block mb-1 text-xs text-gray-500">Speed</label><input type="number" name="scroll_speed" value="50" class="bg-gray-700 border border-gray-600 text-white text-xs rounded p-2 w-full"></div>
                            <div><label class="block mb-1 text-xs text-gray-500">BG Color</label><input type="color" name="bg_color" value="#2563eb" class="w-full h-8 bg-gray-700 border-gray-600 rounded p-1"></div>
                            <div><label class="block mb-1 text-xs text-gray-500">Text Color</label><input type="color" name="text_color" value="#ffffff" class="w-full h-8 bg-gray-700 border-gray-600 rounded p-1"></div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
                        <button type="button" data-modal-hide="create-modal" class="text-gray-400 hover:bg-gray-700 px-5 py-2.5 rounded-lg font-bold">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold">Create Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
</body>
</html>
