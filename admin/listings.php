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

// Handle listing actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET status = 'active' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing approved!';
        }
    }

    if(isset($_POST['reject_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET status = 'rejected' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing rejected!';
        }
    }

    if(isset($_POST['delete_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "DELETE FROM listings WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing deleted!';
        }
    }

    if(isset($_POST['toggle_featured'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET is_featured = NOT is_featured WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Featured status updated!';
        }
    }
}

// Get listing stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM listings");
$stmt->execute();
$total_listings = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM listings WHERE status = 'active'");
$stmt->execute();
$active_listings = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM listings WHERE status = 'pending'");
$stmt->execute();
$pending_listings = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM listings WHERE is_featured = TRUE");
$stmt->execute();
$featured_listings = $stmt->fetch()['count'];

// Pagination and filters
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if($status_filter != 'all') {
    $where_conditions[] = "l.status = :status";
    $params[':status'] = $status_filter;
}

if($search) {
    $where_conditions[] = "(l.title LIKE :search OR l.description LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total
$query = "SELECT COUNT(*) as count FROM listings l LEFT JOIN users u ON l.user_id = u.id $where_clause";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_filtered = $stmt->fetch()['count'];
$total_pages = ceil($total_filtered / $per_page);

// Get listings
$query = "SELECT l.*, u.username, c.name as category_name
          FROM listings l 
          LEFT JOIN users u ON l.user_id = u.id 
          LEFT JOIN categories c ON l.category_id = c.id 
          $where_clause
          ORDER BY l.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status counts
$counts_query = "SELECT status, COUNT(*) as count FROM listings GROUP BY status";
$stmt = $db->prepare($counts_query);
$stmt->execute();
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listing Management - trubl Admin</title>

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
                <li><a href="listings.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg"><i class="fas fa-list w-5 h-5"></i><span class="ml-3">Listings</span></a></li>
                <li><a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-shield-alt w-5 h-5"></i><span class="ml-3">Verifications</span><?php if ($pending_counts['verifications'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse"><?php echo $pending_counts['verifications']; ?></span><?php endif; ?></a></li>
                <li><a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-gem w-5 h-5"></i><span class="ml-3">Upgrades</span><?php if ($pending_counts['upgrades'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo $pending_counts['upgrades']; ?></span><?php endif; ?></a></li>
                <li><a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-flag w-5 h-5"></i><span class="ml-3">Reports</span><?php if ($pending_counts['reports'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo $pending_counts['reports']; ?></span><?php endif; ?></a></li>
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
                            <i class="fas fa-list text-indigo-400 mr-3"></i>
                            Listing Management
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Review and moderate all classified listings</p>
                    </div>
                    <?php if ($pending_listings > 0): ?>
                        <span class="px-4 py-2 bg-orange-500/20 text-orange-400 rounded-lg text-sm font-semibold animate-pulse">
                            <i class="fas fa-clock mr-1"></i>
                            <?php echo $pending_listings; ?> Pending
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
                <div class="p-6 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-list text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($total_listings); ?></h3>
                    <p class="text-indigo-100 text-sm">Total Listings</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-check-circle text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($active_listings); ?></h3>
                    <p class="text-green-100 text-sm">Active Listings</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-clock text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($pending_listings); ?></h3>
                    <p class="text-orange-100 text-sm">Pending Review</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-yellow-500 to-yellow-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-star text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($featured_listings); ?></h3>
                    <p class="text-yellow-100 text-sm">Featured Listings</p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="mb-6 bg-gray-800 border border-gray-700 rounded-lg p-4">
                <form method="GET" class="flex gap-4">
                    <div class="flex-1 relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search listings by title, description, or username..." 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 pl-10 focus:ring-2 focus:ring-indigo-500">
                        <i class="fas fa-search absolute left-3 top-4 text-gray-400"></i>
                    </div>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-semibold rounded-lg transition-all">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <?php if($search): ?>
                        <a href="listings.php" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                    <a href="?status=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 <?php echo $status_filter == 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-list mr-2"></i>All (<?php echo array_sum($status_counts); ?>)
                    </a>
                    <a href="?status=active<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 <?php echo $status_filter == 'active' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-check-circle mr-2"></i>Active (<?php echo $status_counts['active'] ?? 0; ?>)
                    </a>
                    <a href="?status=pending<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 <?php echo $status_filter == 'pending' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-clock mr-2"></i>Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                    </a>
                    <a href="?status=rejected<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 <?php echo $status_filter == 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-times-circle mr-2"></i>Rejected (<?php echo $status_counts['rejected'] ?? 0; ?>)
                    </a>
                </div>
            </div>

            <!-- Listings Grid -->
            <?php if (count($listings) > 0): ?>
                <div class="grid grid-cols-1 gap-4 mb-6">
                    <?php foreach ($listings as $listing): ?>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-indigo-500/50 transition-all">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <h3 class="text-xl font-bold text-white">
                                            <?php echo htmlspecialchars($listing['title']); ?>
                                        </h3>
                                        <?php if($listing['is_featured']): ?>
                                            <span class="px-2 py-1 text-xs bg-yellow-500/20 text-yellow-400 rounded-full border border-yellow-500/50">
                                                <i class="fas fa-star mr-1"></i>Featured
                                            </span>
                                        <?php endif; ?>
                                        <?php
                                        $status_colors = [
                                            'active' => 'bg-green-500/20 text-green-400 border-green-500/50',
                                            'pending' => 'bg-orange-500/20 text-orange-400 border-orange-500/50',
                                            'rejected' => 'bg-red-500/20 text-red-400 border-red-500/50'
                                        ];
                                        $color = $status_colors[$listing['status']] ?? 'bg-gray-500/20 text-gray-400';
                                        ?>
                                        <span class="px-2 py-1 text-xs rounded-full border <?php echo $color; ?>">
                                            <?php echo ucfirst($listing['status']); ?>
                                        </span>
                                    </div>

                                    <p class="text-gray-300 text-sm mb-3 line-clamp-2">
                                        <?php echo htmlspecialchars(substr($listing['description'], 0, 150)); ?>...
                                    </p>

                                    <div class="flex items-center gap-4 text-sm text-gray-400">
                                        <span>
                                            <i class="fas fa-user mr-1"></i>
                                            <?php echo htmlspecialchars($listing['username']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-tag mr-1"></i>
                                            <?php echo htmlspecialchars($listing['category_name'] ?? 'Uncategorized'); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('M j, Y', strtotime($listing['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="ml-4 flex flex-col gap-2">
                                    <a href="../listing.php?id=<?php echo $listing['id']; ?>" target="_blank" 
                                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-all text-center">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>

                                    <form method="POST" class="inline">
                                        <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                        <?php if($listing['status'] == 'pending'): ?>
                                            <button type="submit" name="approve_listing" 
                                                    class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-all">
                                                <i class="fas fa-check mr-1"></i>Approve
                                            </button>
                                            <button type="submit" name="reject_listing" 
                                                    class="w-full px-4 py-2 mt-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-all">
                                                <i class="fas fa-times mr-1"></i>Reject
                                            </button>
                                        <?php endif; ?>

                                        <button type="submit" name="toggle_featured" 
                                                class="w-full px-4 py-2 mt-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm rounded-lg transition-all">
                                            <i class="fas fa-star mr-1"></i><?php echo $listing['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                                        </button>

                                        <button type="submit" name="delete_listing" 
                                                onclick="return confirm('Delete this listing? This cannot be undone.')" 
                                                class="w-full px-4 py-2 mt-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-all">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-2">No Listings Found</h3>
                    <p class="text-gray-400">No listings match your current filters</p>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6 gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 <?php echo $i == $page ? 'bg-indigo-600' : 'bg-gray-800 hover:bg-gray-700'; ?> text-white rounded-lg">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <script>
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
