<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
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

// Handle user actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['suspend_user'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_suspended = TRUE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User suspended successfully!';
        }
    }

    if(isset($_POST['unsuspend_user'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_suspended = FALSE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User unsuspended!';
        }
    }

    if(isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User deleted!';
        }
    }

    if(isset($_POST['make_admin'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_admin = TRUE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User promoted to admin!';
        }
    }

    if(isset($_POST['remove_admin'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_admin = FALSE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'Admin privileges removed!';
        }
    }
}

// Get user stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$total_users = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$new_today = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = TRUE");
$stmt->execute();
$admin_count = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_suspended = TRUE");
$stmt->execute();
$suspended_count = $stmt->fetch()['count'];

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Search
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
if($search) {
    $where_clause = "WHERE username LIKE :search OR email LIKE :search";
    $params[':search'] = "%$search%";
}

// Get total for pagination
$query = "SELECT COUNT(*) as count FROM users $where_clause";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_users_filtered = $stmt->fetch()['count'];
$total_pages = ceil($total_users_filtered / $per_page);

// Get users
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM listings WHERE user_id = u.id) as listing_count,
          (SELECT COUNT(*) FROM messages WHERE sender_id = u.id) as message_count
          FROM users u $where_clause
          ORDER BY u.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - trubl Admin</title>

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
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        ⚡ trubl
                    </span>
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
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt w-5 h-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg">
                        <i class="fas fa-users w-5 h-5"></i>
                        <span class="ml-3">Users</span>
                    </a>
                </li>
                <li>
                    <a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-list w-5 h-5"></i>
                        <span class="ml-3">Listings</span>
                    </a>
                </li>
                <li>
                    <a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                        <i class="fas fa-shield-alt w-5 h-5"></i>
                        <span class="ml-3">Verifications</span>
                        <?php if ($pending_counts['verifications'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
                                <?php echo $pending_counts['verifications']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                        <i class="fas fa-gem w-5 h-5"></i>
                        <span class="ml-3">Upgrades</span>
                        <?php if ($pending_counts['upgrades'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                                <?php echo $pending_counts['upgrades']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                        <i class="fas fa-flag w-5 h-5"></i>
                        <span class="ml-3">Reports</span>
                        <?php if ($pending_counts['reports'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                                <?php echo $pending_counts['reports']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="categories.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-tags w-5 h-5"></i>
                        <span class="ml-3">Categories</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-cog w-5 h-5"></i>
                        <span class="ml-3">Settings</span>
                    </a>
                </li>
            </ul>

            <div class="pt-4 mt-4 space-y-2 border-t border-gray-700">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-home w-5 h-5"></i>
                    <span class="ml-3">Back to Site</span>
                </a>
                <a href="../logout.php" class="flex items-center p-3 text-red-400 rounded-lg hover:bg-red-900/20">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i>
                    <span class="ml-3">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="sm:ml-64">
        <!-- Top Bar -->
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4 py-3 lg:px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-users text-purple-400 mr-3"></i>
                            User Management
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Manage all registered users and their permissions</p>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="p-4 lg:p-6">
            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="p-4 mb-4 text-sm text-green-400 rounded-lg bg-green-900/20 border border-green-500/50" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-4 mb-4 text-sm text-red-400 rounded-lg bg-red-900/20 border border-red-500/50" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="p-6 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg">
                            <i class="fas fa-users text-2xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($total_users); ?></h3>
                    <p class="text-purple-100 text-sm">Total Users</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg">
                            <i class="fas fa-user-plus text-2xl text-white"></i>
                        </div>
                        <span class="text-xs font-semibold text-green-100 bg-green-800/50 px-2 py-1 rounded-full">
                            Today
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($new_today); ?></h3>
                    <p class="text-green-100 text-sm">New Users</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg">
                            <i class="fas fa-user-shield text-2xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($admin_count); ?></h3>
                    <p class="text-blue-100 text-sm">Administrators</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg">
                            <i class="fas fa-ban text-2xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($suspended_count); ?></h3>
                    <p class="text-red-100 text-sm">Suspended Users</p>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="mb-6 bg-gray-800 border border-gray-700 rounded-lg p-4">
                <form method="GET" class="flex gap-4">
                    <div class="flex-1 relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by username or email..." 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 pl-10 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-4 text-gray-400"></i>
                    </div>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-semibold rounded-lg transition-all">
                        <i class="fas fa-search mr-2"></i>
                        Search
                    </button>
                    <?php if($search): ?>
                        <a href="users.php" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all">
                            <i class="fas fa-times mr-2"></i>
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Listings</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Messages</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-4 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 flex items-center justify-center text-white font-bold mr-3">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-white">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                                <?php if($user['is_admin']): ?>
                                                    <span class="ml-2 px-2 py-0.5 text-xs bg-blue-500/20 text-blue-400 rounded">Admin</span>
                                                <?php endif; ?>
                                                <?php if($user['id_verified']): ?>
                                                    <i class="fas fa-check-circle text-green-400 ml-1" title="Verified"></i>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($user['is_suspended']): ?>
                                        <span class="px-3 py-1 text-xs font-semibold text-red-400 bg-red-500/20 rounded-full border border-red-500/50">
                                            <i class="fas fa-ban mr-1"></i> Suspended
                                        </span>
                                    <?php else: ?>
                                        <?php
                                        $is_online = isset($user['last_active']) && strtotime($user['last_active']) > (time() - 300);
                                        ?>
                                        <span class="px-3 py-1 text-xs font-semibold <?php echo $is_online ? 'text-green-400 bg-green-500/20 border-green-500/50' : 'text-gray-400 bg-gray-500/20 border-gray-500/50'; ?> rounded-full border">
                                            <i class="fas fa-circle mr-1 <?php echo $is_online ? 'animate-pulse' : ''; ?>"></i>
                                            <?php echo $is_online ? 'Online' : 'Offline'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-white font-semibold"><?php echo $user['listing_count']; ?></span>
                                    <span class="text-gray-400 text-sm"> listings</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-white font-semibold"><?php echo $user['message_count']; ?></span>
                                    <span class="text-gray-400 text-sm"> messages</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-300"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="../profile.php?id=<?php echo $user['id']; ?>" target="_blank" 
                                           class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded transition-all">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if($user['id'] != $current_user['id']): ?>
                                            <button onclick="toggleActions(<?php echo $user['id']; ?>)" 
                                                    class="px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition-all">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions dropdown -->
                                    <?php if($user['id'] != $current_user['id']): ?>
                                    <div id="actions-<?php echo $user['id']; ?>" class="hidden mt-2 bg-gray-700 rounded-lg shadow-lg p-2 space-y-1">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if($user['is_suspended']): ?>
                                                <button type="submit" name="unsuspend_user" 
                                                        class="w-full text-left px-3 py-2 text-sm text-green-400 hover:bg-gray-600 rounded">
                                                    <i class="fas fa-check mr-2"></i> Unsuspend
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="suspend_user" 
                                                        class="w-full text-left px-3 py-2 text-sm text-orange-400 hover:bg-gray-600 rounded">
                                                    <i class="fas fa-ban mr-2"></i> Suspend
                                                </button>
                                            <?php endif; ?>

                                            <?php if(!$user['is_admin']): ?>
                                                <button type="submit" name="make_admin" 
                                                        class="w-full text-left px-3 py-2 text-sm text-blue-400 hover:bg-gray-600 rounded">
                                                    <i class="fas fa-user-shield mr-2"></i> Make Admin
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="remove_admin" 
                                                        class="w-full text-left px-3 py-2 text-sm text-yellow-400 hover:bg-gray-600 rounded">
                                                    <i class="fas fa-user-minus mr-2"></i> Remove Admin
                                                </button>
                                            <?php endif; ?>

                                            <button type="submit" name="delete_user" 
                                                    onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.')" 
                                                    class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-gray-600 rounded">
                                                <i class="fas fa-trash mr-2"></i> Delete User
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6 gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition-all">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 <?php echo $i == $page ? 'bg-purple-600' : 'bg-gray-800 hover:bg-gray-700'; ?> text-white rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition-all">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>

    <script>
        function toggleActions(userId) {
            const dropdown = document.getElementById('actions-' + userId);
            // Hide all other dropdowns
            document.querySelectorAll('[id^="actions-"]').forEach(el => {
                if(el.id !== 'actions-' + userId) {
                    el.classList.add('hidden');
                }
            });
            dropdown.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if(!e.target.closest('button') && !e.target.closest('[id^="actions-"]')) {
                document.querySelectorAll('[id^="actions-"]').forEach(el => {
                    el.classList.add('hidden');
                });
            }
        });

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
