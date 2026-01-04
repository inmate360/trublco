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

// Handle category actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['create_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']) ?: 'fa-folder';

        if($name) {
            try {
                $query = "INSERT INTO categories (name, description, icon) VALUES (:name, :description, :icon)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':icon', $icon);
                if($stmt->execute()) {
                    $success = 'Category created successfully!';
                }
            } catch(PDOException $e) {
                $error = 'Failed to create category: ' . $e->getMessage();
            }
        }
    }

    if(isset($_POST['update_category'])) {
        $category_id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']) ?: 'fa-folder';

        if($name) {
            $query = "UPDATE categories SET name = :name, description = :description, icon = :icon WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':icon', $icon);
            $stmt->bindParam(':id', $category_id);
            if($stmt->execute()) {
                $success = 'Category updated!';
            }
        }
    }

    if(isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'];

        // Check if category has listings
        $query = "SELECT COUNT(*) as count FROM listings WHERE category_id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $category_id);
        $stmt->execute();
        $listing_count = $stmt->fetch()['count'];

        if($listing_count > 0) {
            $error = "Cannot delete category with {$listing_count} listings. Reassign or delete listings first.";
        } else {
            $query = "DELETE FROM categories WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $category_id);
            if($stmt->execute()) {
                $success = 'Category deleted!';
            }
        }
    }
}

// Get categories with listing counts
$query = "SELECT c.*, COUNT(l.id) as listing_count 
          FROM categories c 
          LEFT JOIN listings l ON c.id = l.category_id 
          GROUP BY c.id 
          ORDER BY c.name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM categories");
$stmt->execute();
$total_categories = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM listings");
$stmt->execute();
$total_listings = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT category_id) as count FROM listings WHERE category_id IS NOT NULL");
$stmt->execute();
$used_categories = $stmt->fetch()['count'];

$empty_categories = $total_categories - $used_categories;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - trubl Admin</title>

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
                <li><a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-flag w-5 h-5"></i><span class="ml-3">Reports</span><?php if ($pending_counts['reports'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo $pending_counts['reports']; ?></span><?php endif; ?></a></li>
                <li><a href="categories.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg"><i class="fas fa-tags w-5 h-5"></i><span class="ml-3">Categories</span></a></li>
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
                            <i class="fas fa-tags text-pink-400 mr-3"></i>
                            Category Management
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Manage listing categories and organization</p>
                    </div>
                    <button data-modal-target="create-modal" data-modal-toggle="create-modal" 
                            class="px-4 py-2 bg-gradient-to-r from-pink-600 to-pink-700 hover:from-pink-700 hover:to-pink-800 text-white font-semibold rounded-lg transition-all">
                        <i class="fas fa-plus mr-2"></i>Create Category
                    </button>
                </div>
            </div>
        </nav>

        <main class="p-4 lg:p-6">
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
                <div class="p-6 bg-gradient-to-br from-pink-500 to-pink-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-tags text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($total_categories); ?></h3>
                    <p class="text-pink-100 text-sm">Total Categories</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-check-circle text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($used_categories); ?></h3>
                    <p class="text-purple-100 text-sm">Active Categories</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-list text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($total_listings); ?></h3>
                    <p class="text-blue-100 text-sm">Total Listings</p>
                </div>

                <div class="p-6 bg-gradient-to-br from-gray-500 to-gray-700 rounded-xl shadow-lg hover:scale-105 transition-transform">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-lg mb-4">
                        <i class="fas fa-inbox text-2xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-white mb-1"><?php echo number_format($empty_categories); ?></h3>
                    <p class="text-gray-100 text-sm">Empty Categories</p>
                </div>
            </div>

            <!-- Categories Grid -->
            <?php if (count($categories) > 0): ?>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($categories as $category): ?>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-pink-500/50 transition-all">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-pink-500 to-pink-700 rounded-lg">
                                    <i class="fas <?php echo htmlspecialchars($category['icon']); ?> text-2xl text-white"></i>
                                </div>
                                <span class="px-3 py-1 text-xs font-semibold bg-pink-500/20 text-pink-400 rounded-full border border-pink-500/50">
                                    <?php echo $category['listing_count']; ?> listings
                                </span>
                            </div>

                            <h3 class="text-xl font-bold text-white mb-2">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h3>

                            <p class="text-gray-400 text-sm mb-4">
                                <?php echo htmlspecialchars($category['description'] ?: 'No description'); ?>
                            </p>

                            <div class="flex gap-2">
                                <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                        class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-all">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>

                                <form method="POST" class="flex-1" onsubmit="return confirm('Delete this category?')">
                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" name="delete_category" 
                                            class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-all">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-12 text-center">
                    <i class="fas fa-tags text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-2">No Categories Yet</h3>
                    <p class="text-gray-400 mb-4">Create your first category to organize listings</p>
                    <button data-modal-target="create-modal" data-modal-toggle="create-modal" 
                            class="px-6 py-3 bg-gradient-to-r from-pink-600 to-pink-700 hover:from-pink-700 hover:to-pink-800 text-white font-semibold rounded-lg transition-all">
                        <i class="fas fa-plus mr-2"></i>Create First Category
                    </button>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Create Modal -->
    <div id="create-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-gray-800 rounded-lg shadow border border-gray-700">
                <div class="flex items-center justify-between p-4 md:p-5 border-b border-gray-700 rounded-t">
                    <h3 class="text-xl font-semibold text-white">
                        <i class="fas fa-plus-circle text-pink-400 mr-2"></i>
                        Create New Category
                    </h3>
                    <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-700 hover:text-white rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="create-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="POST" class="p-4 md:p-5">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Category Name *</label>
                        <input type="text" name="name" required 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-pink-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea name="description" rows="3" 
                                  class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-pink-500"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Icon (Font Awesome class)</label>
                        <input type="text" name="icon" placeholder="fa-folder" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-pink-500">
                        <p class="text-xs text-gray-400 mt-1">Examples: fa-car, fa-home, fa-phone, fa-laptop</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" name="create_category" 
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-pink-600 to-pink-700 hover:from-pink-700 hover:to-pink-800 text-white font-semibold rounded-lg transition-all">
                            <i class="fas fa-save mr-2"></i>Create Category
                        </button>
                        <button type="button" data-modal-hide="create-modal" 
                                class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-gray-800 rounded-lg shadow border border-gray-700">
                <div class="flex items-center justify-between p-4 md:p-5 border-b border-gray-700 rounded-t">
                    <h3 class="text-xl font-semibold text-white">
                        <i class="fas fa-edit text-blue-400 mr-2"></i>
                        Edit Category
                    </h3>
                    <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-700 hover:text-white rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="edit-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="POST" class="p-4 md:p-5">
                    <input type="hidden" name="category_id" id="edit_category_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Category Name *</label>
                        <input type="text" name="name" id="edit_name" required 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea name="description" id="edit_description" rows="3" 
                                  class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Icon (Font Awesome class)</label>
                        <input type="text" name="icon" id="edit_icon" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" name="update_category" 
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-lg transition-all">
                            <i class="fas fa-save mr-2"></i>Update Category
                        </button>
                        <button type="button" data-modal-hide="edit-modal" 
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
        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_icon').value = category.icon || 'fa-folder';

            const modal = new Modal(document.getElementById('edit-modal'));
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
